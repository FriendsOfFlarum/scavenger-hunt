<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\huntmap;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Extension\Event\Disabled;
use Flarum\Extension\ExtensionManager;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\HuntMap\HuntMapRepository;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;

class HuntMapTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-12-01 12:00:00'));

        $this->extension('fof-scavenger-hunt');

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->moderatorUser()],
            'group_user' => [$this->moderatorMembership()],
            'fof_scavenger_hunts' => [
                $this->huntRow(['id' => 1, 'slug' => 'live']),
                $this->huntRow(['id' => 2, 'slug' => 'disabled', 'is_enabled' => false]),
                $this->huntRow(['id' => 3, 'slug' => 'scheduled', 'starts_at' => '2026-12-01 12:10:00']),
                $this->huntRow(['id' => 4, 'slug' => 'ending', 'ends_at' => '2026-12-01 12:30:00', 'guests_can_see' => false]),
            ],
            'fof_scavenger_parts' => [
                $this->partRow(['id' => 1, 'hunt_id' => 1]),
                $this->partRow(['id' => 2, 'hunt_id' => 1, 'mode' => 'wandering', 'wander_chance' => 20, 'sort' => 1]),
                $this->partRow(['id' => 3, 'hunt_id' => 2]),
                $this->partRow(['id' => 4, 'hunt_id' => 3]),
            ],
            'fof_scavenger_locations' => [
                $this->locationRow(['id' => 1, 'part_id' => 1, 'route' => 'index']),
                $this->locationRow(['id' => 2, 'part_id' => 1, 'spot' => 'gone.from.a.disabled.extension']),
                $this->locationRow(['id' => 3, 'part_id' => 2, 'spot' => 'page.footer']),
                $this->locationRow(['id' => 4, 'part_id' => 3]),
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function repository(): HuntMapRepository
    {
        return $this->app()->getContainer()->make(HuntMapRepository::class);
    }

    #[Test]
    public function only_active_hunts_and_registered_spots_are_mapped(): void
    {
        $map = $this->repository()->get();

        $this->assertSame([1, 4], array_keys($map->hunts));
        $this->assertFalse($map->hunt(4)['guests_can_see']);
        $this->assertSame(str_repeat('5eed', 16), $map->hunt(1)['seed']);
        $this->assertSame([1, 2], $map->partIdsOf(1));
        $this->assertSame(2, $map->partCount(1));
        $this->assertSame(20, $map->part(2)['wander_chance']);
        $this->assertSame([1], array_column($map->locationsOf(1), 'id'));
        $this->assertSame('page.footer', $map->locationsOf(2)[0]['spot']);
        $this->assertNull($map->part(3));
    }

    #[Test]
    public function the_map_expires_at_the_next_schedule_boundary(): void
    {
        [, $ttl] = $this->repository()->build(Carbon::now());

        $this->assertSame(600, $ttl);
    }

    #[Test]
    public function a_scheduled_hunt_starts_on_time_without_a_cache_clear(): void
    {
        $this->assertNull($this->repository()->get()->hunt(3));

        Carbon::setTestNow(Carbon::parse('2026-12-01 12:10:01'));

        $this->assertNotNull($this->repository()->get()->hunt(3));
    }

    #[Test]
    public function changes_through_the_api_clear_the_map(): void
    {
        $this->assertNotNull($this->repository()->get()->hunt(1));

        $response = $this->send($this->request('PATCH', '/api/scavenger-hunts/1', [
            'authenticatedAs' => 3,
            'json' => ['data' => ['type' => 'scavenger-hunts', 'id' => '1', 'attributes' => ['isEnabled' => false]]],
        ]));
        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $this->assertNull($this->repository()->get()->hunt(1));
    }

    #[Test]
    public function a_map_built_before_a_change_is_never_trusted_after_it(): void
    {
        $repository = $this->repository();
        $stale = $repository->get()->toArray();

        // A manager disables the Hunt...
        $this->database()->table('fof_scavenger_hunts')->where('id', 1)->update(['is_enabled' => false]);
        $repository->forget();

        // ...while a slow request that started before the save writes back its old copy.
        $this->app()->getContainer()->make(Repository::class)->put(HuntMapRepository::CACHE_KEY, ['generation' => 0, 'map' => $stale], 600);

        $this->assertNull($repository->get()->hunt(1));
    }

    #[Test]
    public function enabling_or_disabling_an_extension_clears_the_map(): void
    {
        $repository = $this->repository();
        $this->assertNotNull($repository->get()->hunt(1));

        $this->database()->table('fof_scavenger_hunts')->where('id', 1)->update(['is_enabled' => false]);

        $extension = $this->app()->getContainer()->make(ExtensionManager::class)->getExtension('fof-scavenger-hunt');
        $this->app()->getContainer()->make(Dispatcher::class)->dispatch(new Disabled($extension));

        $this->assertNull($repository->get()->hunt(1));
    }

    #[Test]
    public function the_summary_follows_the_map(): void
    {
        $repository = $this->repository();

        $this->assertSame([1, 4], array_column($repository->summary(), 'id'));
        $this->assertSame(2, $repository->summary()[0]['part_count']);

        $this->database()->table('fof_scavenger_hunts')->where('id', 1)->update(['is_enabled' => false]);
        $repository->forget();

        $this->assertSame([4], array_column($repository->summary(), 'id'));
    }
}
