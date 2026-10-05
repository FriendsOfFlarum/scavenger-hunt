<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Lookup;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use FoF\ScavengerHunt\Access\HuntAccess;
use FoF\ScavengerHunt\Encounter;
use FoF\ScavengerHunt\Find;
use FoF\ScavengerHunt\HidingSpot\HidingSpotRegistry;
use FoF\ScavengerHunt\HidingSpot\ReportedPage;
use FoF\ScavengerHunt\HidingSpot\ReportedSpot;
use FoF\ScavengerHunt\HuntMap\HuntMap;
use FoF\ScavengerHunt\HuntMap\HuntMapRepository;
use FoF\ScavengerHunt\Part;
use FoF\ScavengerHunt\PlacementMode;
use FoF\ScavengerHunt\Setting;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;

/**
 * Decides which Parts appear in the spots a page reports, and issues Claim tokens for them.
 *
 * @phpstan-import-type PartEntry from HuntMap
 * @phpstan-import-type LocationEntry from HuntMap
 * @phpstan-type Match array{0: LocationEntry, 1: ReportedSpot}
 */
class EncounterResolver
{
    public function __construct(
        protected HuntMapRepository $maps,
        protected HidingSpotRegistry $spots,
        protected HuntAccess $access,
        protected RandomSource $random,
        protected SettingsRepositoryInterface $settings,
        protected Container $container,
    ) {
    }

    /**
     * Which of a Part's Locations a member is given in Assigned mode: the one with the highest
     * score keyed by the Hunt's secret seed (rendezvous hashing). It cannot be worked out from
     * outside the server, it is stable for each member, and adding or removing a Location only
     * moves the members whose pick was that Location.
     *
     * @param non-empty-list<int> $locationIds
     */
    public static function assignedLocation(string $seed, int $partId, int $userId, array $locationIds): int
    {
        $best = $locationIds[0];
        $bestScore = '';

        foreach ($locationIds as $locationId) {
            $score = hash_hmac('sha256', "$partId:$userId:$locationId", $seed);

            if (strcmp($score, $bestScore) > 0) {
                $best = $locationId;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * @return list<ResolvedEncounter>
     */
    public function resolve(User $actor, ReportedPage $page): array
    {
        if ($page->spots === [] || ! $this->access->canSee($actor)) {
            return [];
        }

        $map = $this->maps->get();
        $candidates = $this->candidates($map, $actor, $page);

        // The common case — nothing hidden on this page — ends here without touching the database.
        if ($candidates === []) {
            return [];
        }

        if (! $actor->isGuest()) {
            $found = Find::query()
                ->where('user_id', $actor->id)
                ->whereIn('part_id', array_keys($candidates))
                ->pluck('part_id')
                ->all();

            $candidates = array_diff_key($candidates, array_flip($found));
        }

        $candidates = $this->withValidItems($candidates, $actor, $page);

        if ($candidates === []) {
            return [];
        }

        $canClaim = $this->access->canClaim($actor);
        $open = $canClaim ? $this->openEncounters($actor, array_keys($candidates)) : [];
        $chosen = [];

        foreach ($candidates as $partId => $matches) {
            /** @var PartEntry $part */
            $part = $map->part($partId);

            $match = $part['mode'] === PlacementMode::Wandering->value
                ? $this->wandering($part, $matches, $actor, $open[$partId] ?? [])
                : $this->pick($matches, $actor, $partId);

            if ($match !== null) {
                $chosen[] = ['part' => $part, 'location' => $match[0], 'reported' => $match[1]];
            }
        }

        usort($chosen, fn (array $a, array $b) => $this->rank($a['part']) <=> $this->rank($b['part']));

        $chosen = array_slice($chosen, 0, max(1, (int) $this->settings->get(Setting::MAX_PARTS_PER_PAGE)));

        return $this->issue($actor, $canClaim, $chosen, $open);
    }

    /**
     * Matches of the map's Locations against the report, worked out in memory.
     *
     * @return array<int, non-empty-list<Match>>
     */
    protected function candidates(HuntMap $map, User $actor, ReportedPage $page): array
    {
        $reportedBySpot = [];

        foreach ($page->spots as $reported) {
            $reportedBySpot[$reported->spot][] = $reported;
        }

        $candidates = [];

        foreach ($map->parts as $part) {
            $hunt = $map->hunt($part['hunt_id']);
            $locations = $map->locationsOf($part['id']);

            if ($hunt === null || $locations === []) {
                continue;
            }

            if ($actor->isGuest() && (! $hunt['guests_can_see'] || $part['mode'] === PlacementMode::Assigned->value)) {
                continue;
            }

            if ($part['mode'] === PlacementMode::Assigned->value) {
                $assigned = self::assignedLocation($hunt['seed'], $part['id'], $actor->id, array_column($locations, 'id'));
                $locations = array_values(array_filter($locations, fn (array $location) => $location['id'] === $assigned));
            }

            foreach ($locations as $location) {
                if ($location['route'] !== null && $location['route'] !== $page->route) {
                    continue;
                }

                foreach ($reportedBySpot[$location['spot']] ?? [] as $reported) {
                    if ($location['context_id'] === null || $location['context_id'] === $reported->contextId) {
                        $candidates[$part['id']][] = [$location, $reported];
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * Drops matches whose reported item does not exist, is hidden from the actor, or does not
     * belong to the reported page. One query per kind of item and page constraint.
     *
     * @param array<int, non-empty-list<Match>> $candidates
     * @return array<int, non-empty-list<Match>>
     */
    protected function withValidItems(array $candidates, User $actor, ReportedPage $page): array
    {
        $groups = [];

        foreach ($candidates as $matches) {
            foreach ($matches as [, $reported]) {
                if ($reported->contextType !== null) {
                    $group = $this->itemGroup($reported);
                    $groups[$group]['type'] = $reported->contextType;
                    $groups[$group]['constrain'] = $this->spots->get($reported->spot)?->constrain;
                    $groups[$group]['ids'][$reported->contextId] = true;
                }
            }
        }

        $valid = [];

        foreach ($groups as $group => $wanted) {
            foreach ($this->existingItems($actor, $page, $wanted['type'], $wanted['constrain'], array_keys($wanted['ids'])) as $id) {
                $valid["$group|$id"] = true;
            }
        }

        $kept = [];

        foreach ($candidates as $partId => $matches) {
            $matches = array_values(array_filter(
                $matches,
                fn (array $match) => $match[1]->contextType === null || isset($valid[$this->itemGroup($match[1]).'|'.$match[1]->contextId])
            ));

            if ($matches !== []) {
                $kept[$partId] = $matches;
            }
        }

        return $kept;
    }

    protected function itemGroup(ReportedSpot $reported): string
    {
        return $reported->contextType.'|'.($this->spots->get($reported->spot)->constrain ?? '');
    }

    /**
     * @param list<int> $ids
     * @return list<int>
     */
    protected function existingItems(User $actor, ReportedPage $page, string $type, ?string $constrain, array $ids): array
    {
        $class = $this->spots->contextModel($type);

        if ($class === null) {
            return [];
        }

        /** @var Builder $query */
        $query = $class::query();
        $model = $query->getModel();

        if (method_exists($model, 'scopeWhereVisibleTo')) {
            $query->whereVisibleTo($actor);
        }

        if ($constrain !== null) {
            ($this->container->make($constrain))($query, $page);
        }

        $key = $model->getQualifiedKeyName();

        return $query->whereIn($key, $ids)->pluck($key)->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param list<int> $partIds
     * @return array<int, list<Encounter>>
     */
    protected function openEncounters(User $actor, array $partIds): array
    {
        $open = [];

        $encounters = Encounter::query()
            ->where('user_id', $actor->id)
            ->whereIn('part_id', $partIds)
            ->whereNull('claimed_at')
            ->where('expires_at', '>', Carbon::now())
            ->orderBy('id')
            ->get();

        foreach ($encounters as $encounter) {
            $open[$encounter->part_id][] = $encounter;
        }

        return $open;
    }

    /**
     * A Wandering Part keeps its open Encounter instead of re-rolling, and only one can be open,
     * so refreshing a page or opening several tabs never improves the odds.
     *
     * @param PartEntry $part
     * @param non-empty-list<Match> $matches
     * @param list<Encounter> $open
     * @return Match|null
     */
    protected function wandering(array $part, array $matches, User $actor, array $open): ?array
    {
        if ($open !== []) {
            $openLocations = array_map(fn (Encounter $encounter) => $encounter->location_id, $open);
            $here = array_values(array_filter($matches, fn (array $match) => in_array($match[0]['id'], $openLocations, true)));

            return $here === [] ? null : $this->pick($here, $actor, $part['id']);
        }

        return $this->random->between(1, 100) <= (int) $part['wander_chance']
            ? $this->pick($matches, $actor, $part['id'])
            : null;
    }

    /**
     * A stable choice among several matching items, so a Part stays put while the actor stays on the page.
     *
     * @param non-empty-list<Match> $matches
     * @return Match
     */
    protected function pick(array $matches, User $actor, int $partId): array
    {
        usort($matches, fn (array $a, array $b) => [$a[0]['id'], $a[1]->contextId ?? 0] <=> [$b[0]['id'], $b[1]->contextId ?? 0]);

        $fingerprint = implode(',', array_map(fn (array $match) => $match[0]['id'].':'.($match[1]->contextId ?? 0), $matches));

        return $matches[crc32("$actor->id|$partId|$fingerprint") % count($matches)];
    }

    /**
     * @param PartEntry $part
     * @return list<int>
     */
    protected function rank(array $part): array
    {
        return [PlacementMode::from($part['mode'])->priority(), $part['hunt_id'], $part['sort'], $part['id']];
    }

    /**
     * @param list<array{part: PartEntry, location: LocationEntry, reported: ReportedSpot}> $chosen
     * @param array<int, list<Encounter>> $open
     * @return list<ResolvedEncounter>
     */
    protected function issue(User $actor, bool $canClaim, array $chosen, array $open): array
    {
        if ($chosen === []) {
            return [];
        }

        $parts = Part::query()
            ->with('hunt')
            ->whereIn('id', array_map(fn (array $choice) => $choice['part']['id'], $chosen))
            ->get()
            ->keyBy('id');

        $map = $this->maps->get();

        // The response includes each Part's Hunt; its part count is already in the map, which
        // saves a count query per Hunt when the Hunts are serialized as includes.
        foreach ($parts as $part) {
            $part->hunt?->setAttribute('part_count', $map->partCount($part->hunt_id));
        }

        $now = Carbon::now();
        $lifetime = max(1, (int) $this->settings->get(Setting::TOKEN_LIFETIME));
        $resolved = [];

        foreach ($chosen as $choice) {
            $part = $parts->get($choice['part']['id']);

            if ($part === null) {
                continue;
            }

            $token = null;
            $expiresAt = null;

            if ($canClaim) {
                $token = bin2hex(random_bytes(32));

                $encounter = collect($open[$part->id] ?? [])->firstWhere('location_id', $choice['location']['id']) ?? new Encounter();
                $encounter->part_id = $part->id;
                $encounter->user_id = $actor->id;
                $encounter->location_id = $choice['location']['id'];
                $encounter->token_hash = hash('sha256', $token);
                $encounter->created_at = $now;
                $encounter->issued_at_ms = $now->getTimestampMs();
                $encounter->expires_at = $now->copy()->addMinutes($lifetime);
                $encounter->save();

                $expiresAt = $encounter->expires_at;
            }

            $resolved[] = new ResolvedEncounter(
                $part,
                $choice['reported']->spot,
                $choice['reported']->contextType,
                $choice['reported']->contextId,
                $token,
                $expiresAt,
            );
        }

        return $resolved;
    }
}
