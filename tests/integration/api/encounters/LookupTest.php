<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\api\encounters;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Lookup\RandomSource;
use FoF\ScavengerHunt\Permission;
use FoF\ScavengerHunt\Setting;
use FoF\ScavengerHunt\Tests\integration\FixedRandomSource;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\Test;

class LookupTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->moderatorUser(), $this->secondMember()],
            'group_user' => [$this->moderatorMembership()],
            'fof_scavenger_hunts' => [
                $this->huntRow(['id' => 1]),
                $this->huntRow(['id' => 2, 'slug' => 'members-only', 'guests_can_see' => false]),
            ],
            'fof_scavenger_parts' => [
                $this->partRow(['id' => 1, 'hunt_id' => 1]),
                $this->partRow(['id' => 2, 'hunt_id' => 2, 'name' => 'Members-only part']),
            ],
            'fof_scavenger_locations' => [
                $this->locationRow(['id' => 1, 'part_id' => 1, 'spot' => 'page.corner']),
                $this->locationRow(['id' => 2, 'part_id' => 2, 'spot' => 'page.header', 'route' => 'tags']),
            ],
        ]);
    }

    #[Test]
    public function a_member_encounters_a_fixed_part_with_a_claim_token(): void
    {
        $response = $this->lookup(2, 'index', [['spot' => 'page.corner']]);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $body = $this->body($response);

        $this->assertCount(1, $body['data']);
        $encounter = $body['data'][0];
        $this->assertSame('scavenger-hunt-encounters', $encounter['type']);
        $this->assertSame('page.corner', $encounter['attributes']['spot']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $encounter['attributes']['token']);
        $this->assertSame('1', $encounter['relationships']['part']['data']['id']);

        $included = collect($body['included'])->map(fn ($r) => $r['type'].':'.$r['id'])->all();
        $this->assertContains('scavenger-hunt-parts:1', $included);
        $this->assertContains('scavenger-hunts:1', $included);

        $row = $this->database()->table('fof_scavenger_encounters')->first();
        $this->assertSame(hash('sha256', $encounter['attributes']['token']), $row->token_hash);
        $this->assertSame(2, (int) $row->user_id);
        $this->assertSame(1, (int) $row->location_id);
    }

    #[Test]
    public function nothing_appears_on_another_page_or_in_an_unreported_spot(): void
    {
        $this->assertSame([], $this->body($this->lookup(2, 'index', [['spot' => 'page.header']]))['data']);
        $this->assertCount(1, $this->body($this->lookup(2, 'tags', [['spot' => 'page.header']]))['data']);
        $this->assertSame(0, $this->database()->table('fof_scavenger_encounters')->where('part_id', 1)->count());
    }

    #[Test]
    public function a_part_in_the_search_window_appears_only_once_search_is_opened(): void
    {
        $this->prepareDatabase([
            'fof_scavenger_parts' => [$this->partRow(['id' => 3, 'hunt_id' => 1, 'name' => 'Search part'])],
            'fof_scavenger_locations' => [$this->locationRow(['id' => 3, 'part_id' => 3, 'spot' => 'search.modal', 'route' => 'tags'])],
        ]);

        $partIds = fn (array $data) => array_map(fn (array $encounter) => $encounter['relationships']['part']['data']['id'], $data);

        // The page alone, or search opened on another page, shows nothing.
        $this->assertNotContains('3', $partIds($this->body($this->lookup(2, 'tags', [['spot' => 'page.corner']]))['data']));
        $this->assertNotContains('3', $partIds($this->body($this->lookup(2, 'index', [['spot' => 'search.modal']]))['data']));

        $data = $this->body($this->lookup(2, 'tags', [['spot' => 'search.modal']]))['data'];

        $this->assertSame(['3'], $partIds($data));
        $this->assertSame('search.modal', $data[0]['attributes']['spot']);
    }

    #[Test]
    public function a_found_part_does_not_appear_again(): void
    {
        $this->prepareDatabase([
            'fof_scavenger_finds' => [['part_id' => 1, 'user_id' => 2, 'found_at' => Carbon::now()->toDateTimeString()]],
        ]);

        $this->assertSame([], $this->body($this->lookup(2, 'index', [['spot' => 'page.corner']]))['data']);
    }

    #[Test]
    public function a_disabled_hunt_hides_its_parts(): void
    {
        $this->prepareDatabase(['fof_scavenger_hunts' => [$this->huntRow(['id' => 1, 'is_enabled' => false])]]);

        $this->assertSame([], $this->body($this->lookup(2, 'index', [['spot' => 'page.corner']]))['data']);
    }

    #[Test]
    public function guests_see_parts_without_tokens_and_leave_no_rows(): void
    {
        $body = $this->body($this->lookup(null, 'index', [['spot' => 'page.corner']]));

        $this->assertCount(1, $body['data']);
        $this->assertNull($body['data'][0]['attributes']['token']);
        $this->assertSame(0, $this->database()->table('fof_scavenger_encounters')->count());
    }

    #[Test]
    public function guests_do_not_see_members_only_hunts(): void
    {
        $this->assertSame([], $this->body($this->lookup(null, 'tags', [['spot' => 'page.header']]))['data']);
    }

    #[Test]
    public function hunt_managers_see_parts_without_tokens_by_default(): void
    {
        $body = $this->body($this->lookup(3, 'index', [['spot' => 'page.corner']]));

        $this->assertCount(1, $body['data']);
        $this->assertNull($body['data'][0]['attributes']['token']);
    }

    #[Test]
    public function hunt_managers_can_play_when_the_forum_allows_it(): void
    {
        $this->setting(Setting::MANAGERS_CAN_PLAY, true);

        $body = $this->body($this->lookup(3, 'index', [['spot' => 'page.corner']]));

        $this->assertNotNull($body['data'][0]['attributes']['token']);
    }

    #[Test]
    public function members_without_the_play_permission_see_nothing(): void
    {
        $this->database()->table('group_permission')->where('permission', Permission::PLAY)->delete();

        $this->assertSame([], $this->body($this->lookup(2, 'index', [['spot' => 'page.corner']]))['data']);
    }

    #[Test]
    public function looking_again_rotates_the_token_of_the_same_encounter(): void
    {
        $first = $this->body($this->lookup(2, 'index', [['spot' => 'page.corner']]))['data'][0]['attributes']['token'];
        $second = $this->body($this->lookup(2, 'index', [['spot' => 'page.corner']]))['data'][0]['attributes']['token'];

        $this->assertNotSame($first, $second);
        $this->assertSame(1, $this->database()->table('fof_scavenger_encounters')->count());
        $this->assertSame(hash('sha256', $second), $this->database()->table('fof_scavenger_encounters')->value('token_hash'));
    }

    #[Test]
    public function a_page_shows_at_most_the_configured_number_of_parts_fixed_first(): void
    {
        $this->app()->getContainer()->instance(RandomSource::class, new FixedRandomSource(1));

        $this->prepareDatabase([
            'fof_scavenger_parts' => [
                $this->partRow(['id' => 3, 'hunt_id' => 1, 'name' => 'Wanderer', 'mode' => 'wandering', 'wander_chance' => 100, 'sort' => 0]),
                $this->partRow(['id' => 4, 'hunt_id' => 1, 'name' => 'Second fixed', 'sort' => 5]),
            ],
            'fof_scavenger_locations' => [
                $this->locationRow(['id' => 3, 'part_id' => 3]),
                $this->locationRow(['id' => 4, 'part_id' => 4]),
            ],
        ]);

        $one = $this->body($this->lookup(2, 'index', [['spot' => 'page.corner']]))['data'];
        $this->assertSame(['1'], array_map(fn ($e) => $e['relationships']['part']['data']['id'], $one));
    }

    #[Test]
    public function the_per_page_cap_is_configurable(): void
    {
        $this->setting(Setting::MAX_PARTS_PER_PAGE, 2);

        $this->prepareDatabase([
            'fof_scavenger_parts' => [$this->partRow(['id' => 4, 'hunt_id' => 1, 'name' => 'Second fixed', 'sort' => 5])],
            'fof_scavenger_locations' => [$this->locationRow(['id' => 4, 'part_id' => 4])],
        ]);

        $two = $this->body($this->lookup(2, 'index', [['spot' => 'page.corner']]))['data'];
        $this->assertSame(['1', '4'], array_map(fn ($e) => $e['relationships']['part']['data']['id'], $two));
    }

    #[Test]
    public function a_page_with_nothing_hidden_costs_no_scavenger_queries(): void
    {
        $this->lookup(2, 'index', [['spot' => 'page.footer']]);

        $this->database()->enableQueryLog();
        $this->database()->flushQueryLog();

        $response = $this->lookup(2, 'index', [['spot' => 'page.footer']]);

        $this->assertSame([], $this->body($response)['data']);
        $scavengerQueries = array_filter(
            $this->database()->getQueryLog(),
            fn (array $query) => str_contains($query['query'], 'fof_scavenger')
        );
        $this->assertSame([], array_values($scavengerQueries));
    }
}
