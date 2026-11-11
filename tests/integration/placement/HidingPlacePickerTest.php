<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\placement;

use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Hunt;
use FoF\ScavengerHunt\Lookup\RandomSource;
use FoF\ScavengerHunt\Part;
use FoF\ScavengerHunt\Placement\HidingPlacePicker;
use FoF\ScavengerHunt\Tests\integration\FixedRandomSource;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use FoF\ScavengerHunt\Tests\integration\SeedsForumContent;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\Test;

class HidingPlacePickerTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;
    use SeedsForumContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $discussions = [];
        $posts = [];

        for ($d = 1; $d <= 4; $d++) {
            $discussions[] = $this->discussionRow($d);
            $posts[] = $this->postRow($d * 10 + 1, $d, 1);
            $posts[] = $this->postRow($d * 10 + 2, $d, 2, ['user_id' => 4]);
        }

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->secondMember()],
            Discussion::class => $discussions,
            Post::class => $posts,
            'fof_scavenger_hunts' => [$this->huntRow()],
            'fof_scavenger_parts' => [
                $this->partRow(['id' => 1, 'mode' => 'fixed', 'sort' => 1]),
                $this->partRow(['id' => 2, 'mode' => 'assigned', 'sort' => 2]),
                $this->partRow(['id' => 3, 'mode' => 'wandering', 'wander_chance' => 50, 'sort' => 3]),
                $this->partRow(['id' => 4, 'mode' => 'fixed', 'sort' => 4]),
                $this->partRow(['id' => 5, 'mode' => 'fixed', 'sort' => 5]),
                $this->partRow(['id' => 6, 'mode' => 'assigned', 'sort' => 6]),
            ],
        ]);
    }

    /**
     * @param list<int> $partIds
     * @return list<array{partId: int, places: list<array<string, mixed>>}>
     */
    protected function suggest(array $partIds, array $exclude = []): array
    {
        $container = $this->app()->getContainer();
        $container->instance(RandomSource::class, new FixedRandomSource(0));

        $parts = Part::query()->whereIn('id', $partIds)->orderBy('id')->get()->all();

        return $container->make(HidingPlacePicker::class)->suggest(Hunt::query()->findOrFail(1), $parts, $exclude);
    }

    /**
     * @param list<array{partId: int, places: list<array<string, mixed>>}> $suggestions
     * @return list<array<string, mixed>>
     */
    protected function places(array $suggestions): array
    {
        return array_merge(...array_column($suggestions, 'places'));
    }

    protected function key(array $place): string
    {
        return "{$place['spot']}|{$place['route']}|{$place['contextType']}|{$place['contextId']}";
    }

    #[Test]
    public function fixed_parts_get_one_place_and_random_parts_three(): void
    {
        $suggestions = $this->suggest([1, 2, 3]);

        $this->assertSame([1, 2, 3], array_column($suggestions, 'partId'));
        $this->assertSame([1, 3, 3], array_map(fn (array $s) => count($s['places']), $suggestions));
    }

    #[Test]
    public function places_are_specific_distinct_and_spread_over_spot_kinds(): void
    {
        $suggestions = $this->suggest([1, 2, 3]);
        $places = $this->places($suggestions);

        $this->assertCount(7, array_unique(array_map($this->key(...), $places)));

        foreach ($places as $place) {
            $this->assertNotSame('page.header', $place['spot']);
            $this->assertNull($place['route']);
            $this->assertIsInt($place['contextId']);
            $this->assertFalse($place['fallback']);
        }

        // Spots are taken in turn across the proposal, so no Part clusters on one kind.
        $this->assertSame([
            ['post.footer'],
            ['post.body-end', 'discussion.hero', 'discussionList.item'],
            ['user.card', 'user.sidebar', 'post.footer'],
        ], array_map(fn (array $s) => array_column($s['places'], 'spot'), $suggestions));

        // A Part's places are on different discussions.
        $part2 = $suggestions[1]['places'];
        $this->assertSame(21, $part2[0]['contextId']);
        $this->assertNotContains($part2[1]['contextId'], [2, $part2[2]['contextId']]);
    }

    #[Test]
    public function the_same_random_source_gives_the_same_proposal(): void
    {
        $this->assertSame($this->suggest([1, 2, 3, 6]), $this->suggest([1, 2, 3, 6]));
    }

    #[Test]
    public function excluded_places_are_never_proposed(): void
    {
        $first = $this->suggest([1])[0]['places'][0];
        $this->assertSame(['post.footer', 11], [$first['spot'], $first['contextId']]);

        $again = $this->suggest([1], [['spot' => 'post.footer', 'route' => null, 'contextType' => 'post', 'contextId' => 11]]);

        $this->assertNotSame($this->key($first), $this->key($again[0]['places'][0]));
    }

    #[Test]
    public function places_saved_on_kept_parts_are_never_repeated(): void
    {
        $this->prepareDatabase([
            'fof_scavenger_locations' => [
                $this->locationRow(['id' => 1, 'part_id' => 4, 'spot' => 'post.footer', 'context_type' => 'post', 'context_id' => 11]),
                // Any item: every post body is taken by Part 5.
                $this->locationRow(['id' => 2, 'part_id' => 5, 'spot' => 'post.body-end', 'context_type' => 'post', 'context_id' => null]),
            ],
        ]);

        $places = $this->places($this->suggest([1, 2, 3, 6]));

        $this->assertNotContains('post.footer|||11', array_map($this->key(...), $places));
        $this->assertNotContains('post.body-end', array_column($places, 'spot'));
    }

    #[Test]
    public function a_part_being_replaced_may_reuse_its_own_places(): void
    {
        $this->prepareDatabase([
            'fof_scavenger_locations' => [
                $this->locationRow(['id' => 1, 'part_id' => 1, 'spot' => 'post.footer', 'context_type' => 'post', 'context_id' => 11]),
            ],
        ]);

        $this->assertSame(11, $this->suggest([1])[0]['places'][0]['contextId']);
    }

    #[Test]
    public function places_have_a_label_and_a_forum_url(): void
    {
        $this->app();
        $this->loadTranslations();

        $suggestions = $this->suggest([1, 2, 3]);
        $post = $suggestions[0]['places'][0];

        $this->assertSame('Post footer · post #1 in Discussion 1', $post['label']);
        $this->assertStringEndsWith('/d/1-discussion-1/1', $post['url']);

        $card = $suggestions[2]['places'][0];
        $this->assertSame('User card · member2', $card['label']);
        $this->assertStringEndsWith('/u/member2', $card['url']);

        $list = $suggestions[1]['places'][2];
        $this->assertSame('discussionList.item', $list['spot']);
        $this->assertStringEndsWith('/all', $list['url']);

        foreach ($this->places($suggestions) as $place) {
            $this->assertStringStartsWith('http', $place['url']);
            $this->assertNotSame('', $place['label']);
        }
    }

    #[Test]
    public function a_quiet_forum_gets_page_level_places_marked_as_fallback(): void
    {
        $this->app();
        $this->loadTranslations();
        $this->database()->table('posts')->delete();
        $this->database()->table('discussions')->delete();

        $places = $this->suggest([2])[0]['places'];

        $this->assertCount(3, $places);
        $this->assertEqualsCanonicalizing(['page.footer', 'page.corner', 'page.sidebar'], array_column($places, 'spot'));

        foreach ($places as $place) {
            $this->assertTrue($place['fallback']);
            $this->assertSame('index', $place['route']);
            $this->assertNull($place['contextType']);
            $this->assertNull($place['contextId']);
            $this->assertStringEndsWith('/all', $place['url']);
        }

        $this->assertSame('Page footer · All discussions page', $places[0]['label']);
    }

    #[Test]
    public function the_search_window_is_the_last_page_level_place(): void
    {
        $this->app();
        $this->loadTranslations();
        $this->database()->table('posts')->delete();
        $this->database()->table('discussions')->delete();

        $taken = array_map(
            fn (string $spot) => ['spot' => $spot, 'route' => 'index', 'contextType' => null, 'contextId' => null],
            ['page.footer', 'page.corner', 'page.sidebar'],
        );

        $places = $this->suggest([2], $taken)[0]['places'];

        $this->assertCount(1, $places);
        $this->assertSame('search.modal', $places[0]['spot']);
        $this->assertSame('index', $places[0]['route']);
        $this->assertTrue($places[0]['fallback']);
        $this->assertSame('Search window · All discussions page', $places[0]['label']);
    }

    #[Test]
    public function suggesting_for_more_parts_runs_no_more_queries(): void
    {
        $db = $this->app()->getContainer()->make(ConnectionInterface::class);
        $this->suggest([1, 2]);

        $db->enableQueryLog();

        $db->flushQueryLog();
        $this->suggest([1, 2]);
        $two = count($db->getQueryLog());

        $db->flushQueryLog();
        $this->suggest([1, 2, 3, 4, 5, 6]);
        $six = count($db->getQueryLog());

        $this->assertSame($two, $six);
        $this->assertLessThanOrEqual(10, $two);
    }
}
