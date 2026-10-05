<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\api\hunts;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\Test;

class HuntVisibilityTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->moderatorUser()],
            'group_user' => [$this->moderatorMembership()],
            'fof_scavenger_hunts' => [
                $this->huntRow(['id' => 1, 'slug' => 'live']),
                $this->huntRow(['id' => 2, 'slug' => 'disabled', 'is_enabled' => false]),
                $this->huntRow(['id' => 3, 'slug' => 'scheduled', 'starts_at' => Carbon::now()->addDay()->toDateTimeString()]),
                $this->huntRow(['id' => 4, 'slug' => 'ended', 'starts_at' => Carbon::now()->subDays(9)->toDateTimeString(), 'ends_at' => Carbon::now()->subDay()->toDateTimeString()]),
                $this->huntRow(['id' => 5, 'slug' => 'members-only', 'guests_can_see' => false]),
            ],
        ]);
    }

    protected function visibleSlugs(?int $userId): array
    {
        $response = $this->send($this->request('GET', '/api/scavenger-hunts', $userId ? ['authenticatedAs' => $userId] : []));

        $this->assertEquals(200, $response->getStatusCode());

        $slugs = array_map(fn (array $hunt) => $hunt['attributes']['slug'], $this->body($response)['data']);
        sort($slugs);

        return $slugs;
    }

    #[Test]
    public function guests_see_started_enabled_hunts_open_to_guests(): void
    {
        $this->assertSame(['ended', 'live'], $this->visibleSlugs(null));
    }

    #[Test]
    public function members_also_see_members_only_hunts(): void
    {
        $this->assertSame(['ended', 'live', 'members-only'], $this->visibleSlugs(2));
    }

    #[Test]
    public function hunt_managers_see_every_hunt(): void
    {
        $this->assertSame(['disabled', 'ended', 'live', 'members-only', 'scheduled'], $this->visibleSlugs(3));
    }

    #[Test]
    public function the_seed_is_never_serialized(): void
    {
        $response = $this->send($this->request('GET', '/api/scavenger-hunts/1', ['authenticatedAs' => 1]));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayNotHasKey('seed', $this->body($response)['data']['attributes']);
        $this->assertStringNotContainsString(str_repeat('5eed', 16), (string) $response->getBody());
    }

    #[Test]
    public function manager_fields_are_hidden_from_players(): void
    {
        $attributes = $this->body($this->send($this->request('GET', '/api/scavenger-hunts/1', ['authenticatedAs' => 2])))['data']['attributes'];

        $this->assertArrayNotHasKey('isEnabled', $attributes);
        $this->assertArrayNotHasKey('guestsCanSee', $attributes);
        $this->assertArrayNotHasKey('image', $attributes);
        $this->assertSame('Build a Snowman', $attributes['title']);
        $this->assertTrue($attributes['isActive']);
        $this->assertSame(0, $attributes['foundCount']);
    }

    #[Test]
    public function a_hunt_can_be_fetched_by_slug(): void
    {
        $response = $this->send($this->request('GET', '/api/scavenger-hunts/live', ['authenticatedAs' => 2]));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('1', $this->body($response)['data']['id']);
    }

    #[Test]
    public function an_invisible_hunt_is_not_found(): void
    {
        $response = $this->send($this->request('GET', '/api/scavenger-hunts/2', ['authenticatedAs' => 2]));

        $this->assertEquals(404, $response->getStatusCode());
    }
}
