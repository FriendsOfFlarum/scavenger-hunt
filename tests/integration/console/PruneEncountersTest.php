<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\console;

use Carbon\Carbon;
use Flarum\Testing\integration\ConsoleTestCase;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\Test;

class PruneEncountersTest extends ConsoleTestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $encounter = fn (int $id, Carbon $expiresAt, ?Carbon $claimedAt) => [
            'id' => $id,
            'part_id' => 1,
            'user_id' => 2,
            'location_id' => 1,
            'token_hash' => str_repeat((string) $id, 64),
            'created_at' => $expiresAt->copy()->subMinutes(10)->toDateTimeString(),
            'issued_at_ms' => 0,
            'expires_at' => $expiresAt->toDateTimeString(),
            'claimed_at' => $claimedAt?->toDateTimeString(),
        ];

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'fof_scavenger_hunts' => [$this->huntRow()],
            'fof_scavenger_parts' => [$this->partRow()],
            'fof_scavenger_locations' => [$this->locationRow()],
            'fof_scavenger_encounters' => [
                $encounter(1, Carbon::now()->subDays(8), null),
                $encounter(2, Carbon::now()->subDays(1), null),
                $encounter(3, Carbon::now()->subDays(30), Carbon::now()->subDays(30)),
            ],
        ]);
    }

    #[Test]
    public function only_long_expired_unclaimed_encounters_are_deleted(): void
    {
        $output = $this->runCommand(['command' => 'fof:scavenger-hunt:prune-encounters']);

        $this->assertStringContainsString('1', $output);
        $this->assertSame([2, 3], $this->database()->table('fof_scavenger_encounters')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all());
    }
}
