<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\api\completions;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\Test;

class CompletionsTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $users = [$this->normalUser(), $this->moderatorUser(), $this->secondMember()];
        $hunts = [];
        $completions = [];
        $at = fn (int $minutes) => Carbon::parse('2026-12-01 12:00:00')->addMinutes($minutes)->toDateTimeString();

        // Hunt 1: user 3 first, then 2, then 4. Hunt 2 is disabled.
        $hunts[] = $this->huntRow(['id' => 1, 'slug' => 'snowman']);
        $hunts[] = $this->huntRow(['id' => 2, 'slug' => 'hidden', 'is_enabled' => false]);
        $completions[] = ['hunt_id' => 1, 'user_id' => 3, 'completed_at' => $at(0)];
        $completions[] = ['hunt_id' => 1, 'user_id' => 2, 'completed_at' => $at(5)];
        $completions[] = ['hunt_id' => 1, 'user_id' => 4, 'completed_at' => $at(9)];
        $completions[] = ['hunt_id' => 2, 'user_id' => 2, 'completed_at' => $at(1)];

        // Hunts 3–10: user 2 is second each time, behind user 4.
        for ($id = 3; $id <= 10; $id++) {
            $hunts[] = $this->huntRow(['id' => $id, 'slug' => "hunt-$id", 'title' => "Hunt $id"]);
            $completions[] = ['hunt_id' => $id, 'user_id' => 4, 'completed_at' => $at($id)];
            $completions[] = ['hunt_id' => $id, 'user_id' => 2, 'completed_at' => $at($id + 1)];
        }

        $this->prepareDatabase([
            'users' => $users,
            'group_user' => [$this->moderatorMembership()],
            'fof_scavenger_hunts' => $hunts,
            'fof_scavenger_parts' => [$this->partRow(['id' => 1, 'hunt_id' => 1])],
            'fof_scavenger_completions' => $completions,
        ]);
    }

    protected function completions(?int $userId, array $query): array
    {
        $response = $this->send($this->request('GET', '/api/scavenger-hunt-completions', $userId ? ['authenticatedAs' => $userId] : [])->withQueryParams($query));

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        return $this->body($response);
    }

    #[Test]
    public function first_finishers_are_listed_in_order_with_positions(): void
    {
        $body = $this->completions(null, ['filter' => ['hunt' => '1'], 'include' => 'user']);

        $this->assertSame(['3', '2', '4'], array_map(fn ($c) => $c['relationships']['user']['data']['id'], $body['data']));
        $this->assertSame([1, 2, 3], array_map(fn ($c) => $c['attributes']['position'], $body['data']));
    }

    #[Test]
    public function a_members_trophies_carry_their_position_in_each_hunt_without_n_plus_one(): void
    {
        $body = $this->completions(2, ['filter' => ['user' => '2'], 'include' => 'hunt', 'page' => ['limit' => 50]]);

        $byHunt = [];
        foreach ($body['data'] as $completion) {
            $byHunt[$completion['relationships']['hunt']['data']['id']] = $completion['attributes']['position'];
        }

        $this->assertSame(2, $byHunt['1']);
        $this->assertSame(2, $byHunt['3']);
        $this->assertCount(9, $byHunt);
    }

    #[Test]
    public function completions_of_hunts_the_actor_cannot_see_are_hidden(): void
    {
        $hunts = array_map(fn ($c) => $c['relationships']['hunt']['data']['id'] ?? null, $this->completions(2, ['filter' => ['user' => '2'], 'include' => 'hunt', 'page' => ['limit' => 50]])['data']);
        $this->assertNotContains('2', $hunts);

        $managerView = array_map(fn ($c) => $c['relationships']['hunt']['data']['id'] ?? null, $this->completions(3, ['filter' => ['user' => '2'], 'include' => 'hunt', 'page' => ['limit' => 50]])['data']);
        $this->assertContains('2', $managerView);
    }
}
