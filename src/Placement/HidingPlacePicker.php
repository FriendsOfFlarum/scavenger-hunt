<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Placement;

use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\User;
use FoF\ScavengerHunt\HidingSpot\HidingSpotRegistry;
use FoF\ScavengerHunt\Hunt;
use FoF\ScavengerHunt\Location;
use FoF\ScavengerHunt\Lookup\RandomSource;
use FoF\ScavengerHunt\Part;
use FoF\ScavengerHunt\PlacementMode;

/**
 * Proposes specific hiding places for a Hunt's Parts, among content the Hunt's audience can see.
 * Read-only: nothing is saved until a manager applies the proposal.
 *
 * @phpstan-type Place array{spot: string, route: string|null, contextType: string|null, contextId: int|null, label: string, url: string, fallback: bool}
 * @phpstan-type Exclusion array{spot: string, route: string|null, contextType: string|null, contextId: int|null}
 * @phpstan-type Candidate array{spot: string, route: string|null, contextType: string|null, contextId: int|null, group: string, item: AbstractModel|null, discussion: Discussion|null}
 */
class HidingPlacePicker
{
    /**
     * Spots proposed with a specific item, and their label keys. `page.header` is never proposed:
     * on phones it sits in the closed drawer.
     */
    protected const ITEM_SPOTS = [
        'post.footer' => 'post_footer',
        'post.body-end' => 'post_body_end',
        'discussion.hero' => 'discussion_hero',
        'discussionList.item' => 'discussion_list_item',
        'user.card' => 'user_card',
        'user.sidebar' => 'user_sidebar',
        'tags.tile' => 'tags_tile',
    ];

    /**
     * Page-level spots on specific pages, for when the forum has too little visible content.
     */
    protected const FALLBACK_SPOTS = [
        'page.footer' => 'page_footer',
        'page.corner' => 'page_corner',
        'page.sidebar' => 'page_sidebar',
    ];

    protected const FALLBACK_ROUTES = ['index', 'tags'];

    public function __construct(
        protected HidingSpotRegistry $spots,
        protected HuntAudience $audience,
        protected CandidatePool $pool,
        protected RandomSource $random,
        protected TranslatorInterface $translator,
        protected UrlGenerator $url,
        protected SlugManager $slugs,
    ) {
    }

    public static function placeCount(Part $part): int
    {
        return $part->mode === PlacementMode::Fixed ? 1 : 3;
    }

    /**
     * @param list<Part> $parts Parts of the Hunt to propose places for; the others keep theirs
     * @param list<Exclusion> $exclude places already proposed elsewhere
     * @return list<array{partId: int, places: list<Place>}>
     */
    public function suggest(Hunt $hunt, array $parts, array $exclude = []): array
    {
        if ($parts === []) {
            return [];
        }

        $taken = [...$exclude, ...$this->keptLocations($hunt, $parts)];

        $candidates = $this->candidates($this->pool->load($this->audience->for($hunt)), $taken);
        $fallbacks = array_values(array_filter($this->fallbacks(), fn (array $candidate) => ! $this->isTaken($candidate, $taken)));

        $kinds = array_keys($candidates);
        $cursor = $kinds === [] ? 0 : $this->random->between(0, count($kinds) - 1);

        $chosen = [];
        $proposalGroups = [];
        $fallbackRoutes = [];
        $result = [];

        foreach ($parts as $part) {
            $places = [];
            $partSpots = [];
            $partGroups = [];

            for ($i = 0; $i < self::placeCount($part); $i++) {
                $candidate = $this->pickItem($candidates, $kinds, $cursor, $chosen, $proposalGroups, $partSpots, $partGroups)
                    ?? $this->pickFallback($fallbacks, $chosen, $partSpots, $fallbackRoutes);

                if ($candidate === null) {
                    break;
                }

                $chosen[$this->key($candidate)] = true;
                $proposalGroups[$candidate['group']] = true;
                $partSpots[$candidate['spot']] = true;
                $partGroups[$candidate['group']] = true;

                $places[] = $this->describe($candidate);
            }

            $result[] = ['partId' => $part->id, 'places' => $places];
        }

        return $result;
    }

    /**
     * Saved Locations of the Hunt's other Parts, which are kept as they are.
     *
     * @param list<Part> $parts
     * @return list<Exclusion>
     */
    protected function keptLocations(Hunt $hunt, array $parts): array
    {
        $replaced = array_map(fn (Part $part) => $part->id, $parts);

        return Location::query()
            ->whereIn('part_id', Part::query()->where('hunt_id', $hunt->id)->whereNotIn('id', $replaced)->select('id'))
            ->get(['spot', 'route', 'context_type', 'context_id'])
            ->map(fn (Location $location) => [
                'spot' => $location->spot,
                'route' => $location->route,
                'contextType' => $location->context_type,
                'contextId' => $location->context_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @param list<Exclusion> $taken
     * @return array<string, non-empty-list<Candidate>> by spot, in proposal order
     */
    protected function candidates(Candidates $pool, array $taken): array
    {
        $discussions = [];

        foreach ($pool->discussions as $discussion) {
            $discussions[$discussion->id] = $discussion;
        }

        $items = [
            'post' => [],
            'discussion' => [],
            'user' => [],
            'tag' => [],
        ];

        foreach ($pool->posts as $post) {
            $items['post'][] = [$post, 'discussion:'.$post->discussion_id, $discussions[$post->discussion_id] ?? null];
        }

        foreach ($pool->discussions as $discussion) {
            $items['discussion'][] = [$discussion, 'discussion:'.$discussion->id, $discussion];
        }

        foreach ($pool->users as $user) {
            $items['user'][] = [$user, 'user:'.$user->id, null];
        }

        foreach ($pool->tags as $tag) {
            $items['tag'][] = [$tag, 'tag:'.$tag->getKey(), null];
        }

        $candidates = [];

        foreach (array_keys(self::ITEM_SPOTS) as $key) {
            $spot = $this->spots->get($key);

            if ($spot === null || $spot->context === null || ! isset($items[$spot->context])) {
                continue;
            }

            foreach ($items[$spot->context] as [$item, $group, $discussion]) {
                $candidate = [
                    'spot' => $key,
                    'route' => null,
                    'contextType' => $spot->context,
                    'contextId' => (int) $item->getKey(),
                    'group' => $group,
                    'item' => $item,
                    'discussion' => $discussion,
                ];

                if (! $this->isTaken($candidate, $taken)) {
                    $candidates[$key][] = $candidate;
                }
            }
        }

        return $candidates;
    }

    /**
     * @return list<Candidate>
     */
    protected function fallbacks(): array
    {
        $fallbacks = [];

        foreach (self::FALLBACK_ROUTES as $route) {
            if (! $this->spots->isPageRoute($route)) {
                continue;
            }

            foreach (array_keys(self::FALLBACK_SPOTS) as $key) {
                if ($this->spots->get($key)?->allowsRoute($route)) {
                    $fallbacks[] = ['spot' => $key, 'route' => $route, 'contextType' => null, 'contextId' => null, 'group' => "page:$route", 'item' => null, 'discussion' => null];
                }
            }
        }

        return $fallbacks;
    }

    /**
     * Spots are taken in turn across the whole proposal. A Part's places go to different items,
     * then different spots, and preferably to items no other Part uses, so that Parts don't
     * compete for one page.
     *
     * @param array<string, non-empty-list<Candidate>> $candidates
     * @param list<string> $kinds
     * @param array<string, true> $chosen
     * @param array<string, true> $proposalGroups
     * @param array<string, true> $partSpots
     * @param array<string, true> $partGroups
     * @return Candidate|null
     */
    protected function pickItem(array $candidates, array $kinds, int &$cursor, array $chosen, array $proposalGroups, array $partSpots, array $partGroups): ?array
    {
        $passes = [
            fn (array $c) => ! isset($partSpots[$c['spot']]) && ! isset($proposalGroups[$c['group']]),
            fn (array $c) => ! isset($proposalGroups[$c['group']]),
            fn (array $c) => ! isset($partSpots[$c['spot']]) && ! isset($partGroups[$c['group']]),
            fn (array $c) => ! isset($partGroups[$c['group']]),
            fn (array $c) => true,
        ];

        $count = count($kinds);

        foreach ($passes as $accepts) {
            for ($offset = 0; $offset < $count; $offset++) {
                $index = ($cursor + $offset) % $count;

                $available = array_values(array_filter(
                    $candidates[$kinds[$index]],
                    fn (array $c) => ! isset($chosen[$this->key($c)]) && $accepts($c),
                ));

                if ($available !== []) {
                    $cursor = ($index + 1) % $count;

                    return $available[$this->random->between(0, count($available) - 1)];
                }
            }
        }

        return null;
    }

    /**
     * @param list<Candidate> $fallbacks
     * @param array<string, true> $chosen
     * @param array<string, true> $partSpots
     * @param array<string, int> $fallbackRoutes how often each page already has a fallback place
     * @return Candidate|null
     */
    protected function pickFallback(array $fallbacks, array $chosen, array $partSpots, array &$fallbackRoutes): ?array
    {
        $best = null;
        $bestScore = null;

        foreach ($fallbacks as $candidate) {
            if (isset($chosen[$this->key($candidate)])) {
                continue;
            }

            $score = [isset($partSpots[$candidate['spot']]) ? 1 : 0, $fallbackRoutes[(string) $candidate['route']] ?? 0];

            if ($bestScore === null || $score < $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        if ($best !== null) {
            $fallbackRoutes[(string) $best['route']] = ($fallbackRoutes[(string) $best['route']] ?? 0) + 1;
        }

        return $best;
    }

    /**
     * Whether a place would show where an excluded one does. A null route or item on the
     * excluded side means any route or any item.
     *
     * @param Candidate $candidate
     * @param list<Exclusion> $taken
     */
    protected function isTaken(array $candidate, array $taken): bool
    {
        foreach ($taken as $place) {
            if ($place['spot'] === $candidate['spot']
                && ($place['route'] === null || $candidate['route'] === null || $place['route'] === $candidate['route'])
                && ($place['contextId'] === null || $place['contextId'] === $candidate['contextId'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Candidate $candidate
     */
    protected function key(array $candidate): string
    {
        return $candidate['spot'].'|'.$candidate['route'].'|'.$candidate['contextType'].'|'.$candidate['contextId'];
    }

    /**
     * @param Candidate $candidate
     * @return Place
     */
    protected function describe(array $candidate): array
    {
        [$label, $url] = $this->labelAndUrl($candidate);

        return [
            'spot' => $candidate['spot'],
            'route' => $candidate['route'],
            'contextType' => $candidate['contextType'],
            'contextId' => $candidate['contextId'],
            'label' => $label,
            'url' => $url,
            'fallback' => $candidate['item'] === null,
        ];
    }

    /**
     * @param Candidate $candidate
     * @return array{0: string, 1: string}
     */
    protected function labelAndUrl(array $candidate): array
    {
        $forum = $this->url->to('forum');
        $item = $candidate['item'];

        if ($item === null) {
            $route = (string) $candidate['route'];

            return [
                $this->label(self::FALLBACK_SPOTS[$candidate['spot']], ['page' => $this->translator->trans("fof-scavenger-hunt.lib.place_labels.pages.$route")]),
                $forum->route($route),
            ];
        }

        $labelKey = self::ITEM_SPOTS[$candidate['spot']];

        if ($item instanceof User) {
            return [
                $this->label($labelKey, ['user' => $item->display_name]),
                $forum->route('user', ['username' => $this->slugs->forResource(User::class)->toSlug($item)]),
            ];
        }

        return match ($candidate['contextType']) {
            'post' => [
                $this->label($labelKey, ['number' => $item->getAttribute('number'), 'discussion' => $candidate['discussion']?->title]),
                $forum->route('discussion', ['id' => $this->discussionSlug($candidate['discussion']), 'near' => $item->getAttribute('number')]),
            ],
            'discussion' => [
                $this->label($labelKey, ['discussion' => $item->getAttribute('title')]),
                $candidate['spot'] === 'discussionList.item' ? $forum->route('index') : $forum->route('discussion', ['id' => $this->discussionSlug($candidate['discussion'])]),
            ],
            default => [
                $this->label($labelKey, ['tag' => $item->getAttribute('name')]),
                $forum->route('tags'),
            ],
        };
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function label(string $key, array $parameters): string
    {
        return $this->translator->trans("fof-scavenger-hunt.lib.place_labels.$key", $parameters);
    }

    protected function discussionSlug(?Discussion $discussion): string
    {
        return $discussion === null ? '' : $this->slugs->forResource(Discussion::class)->toSlug($discussion);
    }
}
