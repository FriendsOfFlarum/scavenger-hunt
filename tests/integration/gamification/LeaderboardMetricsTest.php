<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\gamification;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\Test;

class LeaderboardMetricsTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'fof-gamification', 'fof-scavenger-hunt');

        $now = Carbon::now();
        $lastYear = Carbon::now()->subYears(2)->toDateTimeString();

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->secondMember()],
            'fof_scavenger_hunts' => [$this->huntRow()],
            'fof_scavenger_parts' => [
                $this->partRow(['id' => 1]),
                $this->partRow(['id' => 2, 'sort' => 1]),
                $this->partRow(['id' => 3, 'sort' => 2]),
            ],
            'fof_scavenger_finds' => [
                ['part_id' => 1, 'user_id' => 2, 'found_at' => $now->toDateTimeString()],
                ['part_id' => 2, 'user_id' => 2, 'found_at' => $now->toDateTimeString()],
                ['part_id' => 3, 'user_id' => 2, 'found_at' => $now->toDateTimeString()],
                ['part_id' => 1, 'user_id' => 4, 'found_at' => $now->toDateTimeString()],
                ['part_id' => 2, 'user_id' => 4, 'found_at' => $lastYear],
            ],
            'fof_scavenger_completions' => [
                ['hunt_id' => 1, 'user_id' => 2, 'completed_at' => $now->toDateTimeString()],
            ],
        ]);
    }

    protected function ranking(string $metric, string $period): array
    {
        $response = $this->send(
            $this->request('GET', '/api/leaderboard-entries', ['authenticatedAs' => 1])
                ->withQueryParams(['filter' => ['metric' => $metric, 'period' => $period], 'include' => 'user'])
        );

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        return array_map(fn (array $entry) => [$entry['relationships']['user']['data']['id'], $entry['attributes']['score']], $this->body($response)['data']);
    }

    #[Test]
    public function members_are_ranked_by_parts_found(): void
    {
        $this->assertSame([['2', 3], ['4', 2]], $this->ranking('scavenger-hunt.parts-found', 'all'));
        $this->assertSame([['2', 3], ['4', 1]], $this->ranking('scavenger-hunt.parts-found', 'year'));
    }

    #[Test]
    public function members_are_ranked_by_hunts_completed(): void
    {
        $this->assertSame([['2', 1]], $this->ranking('scavenger-hunt.hunts-completed', 'all'));
    }
}
