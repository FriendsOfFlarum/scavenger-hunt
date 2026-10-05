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

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\Test;

class DashboardDataTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $parts = [];
        $locations = [];

        for ($i = 1; $i <= 8; $i++) {
            $parts[] = $this->partRow(['id' => $i, 'name' => "Part $i", 'sort' => $i]);
            $locations[] = $this->locationRow(['id' => $i, 'part_id' => $i]);
        }

        $this->prepareDatabase([
            'users' => [$this->moderatorUser()],
            'group_user' => [$this->moderatorMembership()],
            'fof_scavenger_hunts' => [$this->huntRow()],
            'fof_scavenger_parts' => $parts,
            'fof_scavenger_locations' => $locations,
        ]);
    }

    #[Test]
    public function a_hunt_manager_loads_a_hunt_with_its_parts_and_locations_in_a_few_queries(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/scavenger-hunts/1', ['authenticatedAs' => 3])
                ->withQueryParams(['include' => 'parts,parts.locations'])
        );

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $types = array_count_values(array_column($this->body($response)['included'], 'type'));
        $this->assertSame(8, $types['scavenger-hunt-parts']);
        $this->assertSame(8, $types['scavenger-hunt-locations']);
    }

    #[Test]
    public function the_dashboard_pages_are_not_offered_as_hiding_places(): void
    {
        $response = $this->send($this->request('GET', '/api/scavenger-hunt-spots', ['authenticatedAs' => 3]));

        $routes = array_column($this->body($response)['meta']['routes'], 'name');
        $this->assertNotContains('fof-scavenger-hunt.dashboard', $routes);
        $this->assertNotContains('fof-scavenger-hunt.dashboard.hunt', $routes);
        $this->assertContains('index', $routes);
    }
}
