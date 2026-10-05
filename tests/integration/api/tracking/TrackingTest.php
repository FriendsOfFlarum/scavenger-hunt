<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\api\tracking;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Event\ProgressReset;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;

class TrackingTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $at = fn (string $time) => Carbon::parse("2026-12-01 $time")->toDateTimeString();
        $encounter = fn (int $id, int $part, int $user, ?string $claimed, ?int $ms) => [
            'id' => $id, 'part_id' => $part, 'user_id' => $user, 'location_id' => $part, 'token_hash' => str_repeat(dechex($id), 64 / strlen(dechex($id))),
            'created_at' => $at('10:00:00'), 'issued_at_ms' => 0, 'expires_at' => $at('23:00:00'), 'claimed_at' => $claimed ? $at($claimed) : null, 'claim_ms' => $ms,
        ];

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->moderatorUser(), $this->secondMember()],
            'group_user' => [$this->moderatorMembership()],
            'fof_scavenger_hunts' => [$this->huntRow()],
            'fof_scavenger_parts' => [
                $this->partRow(['id' => 1, 'name' => 'Nose']),
                $this->partRow(['id' => 2, 'name' => 'Hat', 'sort' => 1]),
            ],
            'fof_scavenger_locations' => [
                $this->locationRow(['id' => 1, 'part_id' => 1]),
                $this->locationRow(['id' => 2, 'part_id' => 2, 'spot' => 'page.footer']),
            ],
            // Member 2 finds both (instantly, three times over); member 4 finds one and has one open.
            'fof_scavenger_encounters' => [
                $encounter(1, 1, 2, '10:00:01', 200),
                $encounter(2, 2, 2, '10:05:00', 300),
                $encounter(3, 1, 2, '10:06:00', 400),
                $encounter(4, 1, 4, '11:00:00', 9000),
                $encounter(5, 2, 4, null, null),
            ],
            'fof_scavenger_finds' => [
                ['part_id' => 1, 'user_id' => 2, 'found_at' => $at('10:00:01')],
                ['part_id' => 2, 'user_id' => 2, 'found_at' => $at('10:05:00')],
                ['part_id' => 1, 'user_id' => 4, 'found_at' => $at('11:00:00')],
            ],
            'fof_scavenger_completions' => [
                ['hunt_id' => 1, 'user_id' => 2, 'completed_at' => $at('10:05:00')],
            ],
        ]);
    }

    protected function get(int $userId, string $path, array $query = []): array
    {
        $response = $this->send($this->request('GET', $path, ['authenticatedAs' => $userId])->withQueryParams($query));
        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        return $this->body($response);
    }

    #[Test]
    public function hunt_managers_get_the_numbers_for_a_hunt(): void
    {
        $stats = $this->get(3, '/api/scavenger-hunt-stats/1')['data']['attributes'];

        $this->assertSame(2, $stats['participants']);
        $this->assertSame(3, $stats['finds']);
        $this->assertSame(1, $stats['completions']);
        $this->assertSame(5, $stats['shown']);

        $nose = collect($stats['parts'])->firstWhere('id', 1);
        $this->assertSame(['shown' => 3, 'found' => 2], ['shown' => $nose['shown'], 'found' => $nose['found']]);
        $this->assertSame([['date' => '2026-12-01', 'count' => 1]], $stats['completionsPerDay']);
    }

    #[Test]
    public function participants_are_listed_with_their_progress_and_flags(): void
    {
        $data = $this->get(3, '/api/scavenger-hunt-participants', ['filter' => ['hunt' => '1'], 'include' => 'user'])['data'];

        $this->assertSame(['2', '4'], array_map(fn ($row) => $row['relationships']['user']['data']['id'], $data));

        $first = $data[0]['attributes'];
        $this->assertSame(2, $first['foundCount']);
        $this->assertSame(1, $first['position']);
        $this->assertNotNull($first['completedAt']);
        $this->assertTrue($first['fastClaimFlag']);
        $this->assertSame(3, $first['encounterCount']);

        $this->assertFalse($data[1]['attributes']['fastClaimFlag']);
        $this->assertNull($data[1]['attributes']['completedAt']);
    }

    #[Test]
    public function participants_can_be_searched_and_sorted(): void
    {
        $search = $this->get(3, '/api/scavenger-hunt-participants', ['filter' => ['hunt' => '1', 'q' => 'mem'], 'include' => 'user'])['data'];
        $this->assertSame(['4'], array_map(fn ($row) => $row['relationships']['user']['data']['id'], $search));

        $sorted = $this->get(3, '/api/scavenger-hunt-participants', ['filter' => ['hunt' => '1'], 'sort' => '-lastFoundAt', 'include' => 'user'])['data'];
        $this->assertSame(['4', '2'], array_map(fn ($row) => $row['relationships']['user']['data']['id'], $sorted));
    }

    #[Test]
    public function a_participants_finds_can_be_listed(): void
    {
        $data = $this->get(3, '/api/scavenger-hunt-finds', ['filter' => ['hunt' => '1', 'user' => '2'], 'include' => 'part'])['data'];

        $this->assertCount(2, $data);
    }

    #[Test]
    public function players_cannot_see_tracking(): void
    {
        foreach (['/api/scavenger-hunt-stats/1', '/api/scavenger-hunt-participants?filter[hunt]=1', '/api/scavenger-hunt-finds?filter[hunt]=1'] as $path) {
            [$path, $query] = array_pad(explode('?', $path), 2, '');
            parse_str($query, $params);

            $this->assertEquals(403, $this->send($this->request('GET', $path, ['authenticatedAs' => 2])->withQueryParams($params))->getStatusCode(), $path);
        }
    }

    #[Test]
    public function a_hunt_manager_can_reset_a_members_progress(): void
    {
        $reset = [];
        $this->app()->getContainer()->make(Dispatcher::class)->listen(ProgressReset::class, function (ProgressReset $event) use (&$reset) {
            $reset[] = $event;
        });

        $response = $this->send($this->request('POST', '/api/scavenger-hunts/1/reset-progress', [
            'authenticatedAs' => 3,
            'json' => ['data' => ['attributes' => ['userId' => 4]]],
        ]));

        $this->assertEquals(204, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(0, $this->database()->table('fof_scavenger_finds')->where('user_id', 4)->count());
        $this->assertSame(0, $this->database()->table('fof_scavenger_encounters')->where('user_id', 4)->whereNull('claimed_at')->count());
        $this->assertSame(2, $this->database()->table('fof_scavenger_finds')->where('user_id', 2)->count());
        $this->assertCount(1, $reset);
        $this->assertSame(4, $reset[0]->user->id);
    }

    #[Test]
    public function members_cannot_reset_progress(): void
    {
        $response = $this->send($this->request('POST', '/api/scavenger-hunts/1/reset-progress', [
            'authenticatedAs' => 2,
            'json' => ['data' => ['attributes' => ['userId' => 4]]],
        ]));

        $this->assertEquals(403, $response->getStatusCode());
    }
}
