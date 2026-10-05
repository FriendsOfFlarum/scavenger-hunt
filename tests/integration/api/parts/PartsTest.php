<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\api\parts;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

class PartsTest extends TestCase
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
            'fof_scavenger_hunts' => [$this->huntRow()],
            'fof_scavenger_parts' => [
                $this->partRow(['id' => 1, 'name' => 'Carrot nose', 'hint' => 'Look where the snow talk happens']),
                // A hint stored against a Random Part (e.g. by a direct DB edit) must still never be served.
                $this->partRow(['id' => 2, 'name' => 'Top hat', 'mode' => 'assigned', 'hint' => 'secret', 'sort' => 1]),
            ],
            'fof_scavenger_finds' => [
                ['part_id' => 1, 'user_id' => 2, 'found_at' => Carbon::now()->toDateTimeString()],
            ],
        ]);
    }

    protected function createPart(int $userId, array $attributes): ResponseInterface
    {
        return $this->send($this->request('POST', '/api/scavenger-hunt-parts', [
            'authenticatedAs' => $userId,
            'json' => ['data' => [
                'type' => 'scavenger-hunt-parts',
                'attributes' => $attributes,
                'relationships' => ['hunt' => ['data' => ['type' => 'scavenger-hunts', 'id' => '1']]],
            ]],
        ]));
    }

    protected function updatePart(int $userId, int $id, array $attributes): ResponseInterface
    {
        return $this->send($this->request('PATCH', "/api/scavenger-hunt-parts/$id", [
            'authenticatedAs' => $userId,
            'json' => ['data' => ['type' => 'scavenger-hunt-parts', 'id' => (string) $id, 'attributes' => $attributes]],
        ]));
    }

    protected function showPart(int $userId, int $id): array
    {
        $response = $this->send($this->request('GET', "/api/scavenger-hunt-parts/$id", ['authenticatedAs' => $userId]));

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        return $this->body($response)['data']['attributes'];
    }

    #[Test]
    public function a_hunt_manager_can_create_a_fixed_part_with_a_hint(): void
    {
        $response = $this->createPart(3, ['name' => 'Scarf', 'hint' => 'Somewhere cosy']);

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame('fixed', $this->body($response)['data']['attributes']['mode']);
        $this->assertSame('Somewhere cosy', $this->database()->table('fof_scavenger_parts')->where('name', 'Scarf')->value('hint'));
    }

    #[Test]
    public function a_random_part_cannot_be_given_a_hint(): void
    {
        $response = $this->createPart(3, ['name' => 'Scarf', 'mode' => 'assigned', 'hint' => 'Nope']);

        $this->assertEquals(422, $response->getStatusCode());
    }

    #[Test]
    public function switching_to_a_random_mode_clears_the_hint(): void
    {
        $response = $this->updatePart(3, 1, ['mode' => 'wandering', 'wanderChance' => 25]);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $row = $this->database()->table('fof_scavenger_parts')->find(1);
        $this->assertNull($row->hint);
        $this->assertSame(25, (int) $row->wander_chance);
    }

    #[Test]
    public function a_wandering_part_needs_a_chance(): void
    {
        $this->assertEquals(422, $this->updatePart(3, 1, ['mode' => 'wandering'])->getStatusCode());
    }

    #[Test]
    public function an_out_of_range_layer_is_rejected(): void
    {
        $this->assertEquals(422, $this->updatePart(3, 1, ['layer' => ['x' => 150, 'y' => 0, 'width' => 10, 'z' => 1]])->getStatusCode());
    }

    #[Test]
    public function a_valid_layer_is_stored(): void
    {
        $this->assertEquals(200, $this->updatePart(3, 1, ['layer' => ['x' => 10, 'y' => 20.5, 'width' => 30, 'z' => 2]])->getStatusCode());

        $this->assertEquals(
            ['x' => 10, 'y' => 20.5, 'width' => 30, 'z' => 2],
            json_decode($this->database()->table('fof_scavenger_parts')->where('id', 1)->value('layer'), true)
        );
    }

    #[Test]
    public function members_cannot_create_or_edit_parts(): void
    {
        $this->assertEquals(403, $this->createPart(2, ['name' => 'Nope'])->getStatusCode());
        $this->assertEquals(403, $this->updatePart(2, 1, ['name' => 'Nope'])->getStatusCode());
    }

    #[Test]
    public function players_see_hints_only_on_fixed_parts_and_no_placement_details(): void
    {
        $fixed = $this->showPart(2, 1);
        $this->assertSame('Look where the snow talk happens', $fixed['hint']);
        $this->assertFalse($fixed['isRandom']);

        $random = $this->showPart(2, 2);
        $this->assertArrayNotHasKey('hint', $random);
        $this->assertTrue($random['isRandom']);

        foreach (['mode', 'wanderChance', 'image'] as $managerOnly) {
            $this->assertArrayNotHasKey($managerOnly, $fixed);
            $this->assertArrayNotHasKey($managerOnly, $random);
        }
    }

    #[Test]
    public function is_found_reflects_the_actors_own_finds(): void
    {
        $mine = $this->showPart(2, 1);
        $this->assertTrue($mine['isFound']);
        $this->assertNotNull($mine['foundAt']);

        $theirs = $this->showPart(4, 1);
        $this->assertFalse($theirs['isFound']);
        $this->assertNull($theirs['foundAt']);
    }
}
