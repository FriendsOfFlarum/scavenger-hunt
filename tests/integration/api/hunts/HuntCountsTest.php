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

class HuntCountsTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $hunts = [];
        $parts = [];

        for ($i = 1; $i <= 8; $i++) {
            $hunts[] = $this->huntRow(['id' => $i, 'slug' => "hunt-$i", 'title' => "Hunt $i"]);
            $parts[] = $this->partRow(['id' => 2 * $i - 1, 'hunt_id' => $i, 'sort' => 0]);
            $parts[] = $this->partRow(['id' => 2 * $i, 'hunt_id' => $i, 'sort' => 1]);
        }

        $now = Carbon::now()->toDateTimeString();

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->moderatorUser(), $this->secondMember()],
            'group_user' => [$this->moderatorMembership()],
            'fof_scavenger_hunts' => $hunts,
            'fof_scavenger_parts' => $parts,
            'fof_scavenger_finds' => [
                ['id' => 1, 'part_id' => 1, 'user_id' => 2, 'found_at' => $now],
                ['id' => 2, 'part_id' => 2, 'user_id' => 2, 'found_at' => $now],
                ['id' => 3, 'part_id' => 1, 'user_id' => 4, 'found_at' => $now],
                ['id' => 4, 'part_id' => 3, 'user_id' => 4, 'found_at' => $now],
            ],
            'fof_scavenger_completions' => [
                ['id' => 1, 'hunt_id' => 1, 'user_id' => 2, 'completed_at' => $now],
            ],
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function huntsById(?int $userId, array $query = []): array
    {
        $response = $this->send(
            $this->request('GET', '/api/scavenger-hunts', $userId === null ? [] : ['authenticatedAs' => $userId])
                ->withQueryParams($query)
        );

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        return array_column(array_map(
            fn (array $hunt) => ['id' => $hunt['id']] + $hunt['attributes'],
            $this->body($response)['data']
        ), null, 'id');
    }

    #[Test]
    public function managers_see_participant_and_finisher_counts(): void
    {
        $hunts = $this->huntsById(3);

        $this->assertSame(2, $hunts['1']['participantCount']);
        $this->assertSame(1, $hunts['1']['finisherCount']);
        $this->assertSame(1, $hunts['2']['participantCount']);
        $this->assertSame(0, $hunts['2']['finisherCount']);
        $this->assertSame(0, $hunts['3']['participantCount']);
        $this->assertSame(0, $hunts['3']['finisherCount']);
    }

    #[Test]
    public function a_single_hunt_has_its_counts(): void
    {
        $response = $this->send($this->request('GET', '/api/scavenger-hunts/hunt-1', ['authenticatedAs' => 3]));

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $attributes = $this->body($response)['data']['attributes'];
        $this->assertSame(2, $attributes['participantCount']);
        $this->assertSame(1, $attributes['finisherCount']);
    }

    #[Test]
    public function members_and_guests_see_the_finisher_count_but_not_the_participant_count(): void
    {
        foreach ([2, null] as $userId) {
            $hunt = $this->huntsById($userId)['1'];

            $this->assertSame(1, $hunt['finisherCount']);
            $this->assertArrayNotHasKey('participantCount', $hunt);
        }
    }

    #[Test]
    public function counts_cost_the_same_for_any_page_size(): void
    {
        $count = function (int $limit): int {
            $queries = 0;
            $this->database()->listen(function () use (&$queries) {
                $queries++;
            });

            $this->assertCount($limit, $this->huntsById(3, ['page' => ['limit' => $limit]]));

            return $queries;
        };

        $count(2);

        $this->assertSame($count(2), $count(7));
    }
}
