<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\unit\Lookup;

use FoF\ScavengerHunt\Lookup\EncounterResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AssignedLocationTest extends TestCase
{
    protected const SEED = 'abababababababababababababababababababababababababababababababab';

    #[Test]
    public function the_same_member_always_gets_the_same_location(): void
    {
        $first = EncounterResolver::assignedLocation(self::SEED, 7, 42, [3, 5, 8, 13]);

        for ($i = 0; $i < 10; $i++) {
            $this->assertSame($first, EncounterResolver::assignedLocation(self::SEED, 7, 42, [3, 5, 8, 13]));
        }
    }

    #[Test]
    public function members_are_spread_evenly_across_locations(): void
    {
        $counts = array_fill_keys([11, 12, 13, 14], 0);

        for ($userId = 1; $userId <= 4000; $userId++) {
            $counts[EncounterResolver::assignedLocation(self::SEED, 3, $userId, [11, 12, 13, 14])]++;
        }

        foreach ($counts as $count) {
            $this->assertGreaterThan(850, $count);
            $this->assertLessThan(1150, $count);
        }
    }

    #[Test]
    public function removing_a_location_only_moves_the_members_who_had_it(): void
    {
        for ($userId = 1; $userId <= 1000; $userId++) {
            $before = EncounterResolver::assignedLocation(self::SEED, 3, $userId, [1, 2, 3, 4]);
            $after = EncounterResolver::assignedLocation(self::SEED, 3, $userId, [1, 2, 4]);

            if ($before !== 3) {
                $this->assertSame($before, $after, "member $userId moved");
            }
        }
    }

    #[Test]
    public function a_different_seed_reshuffles_the_assignment(): void
    {
        $a = array_map(fn (int $u) => EncounterResolver::assignedLocation('seed-a', 1, $u, [1, 2, 3]), range(1, 50));
        $b = array_map(fn (int $u) => EncounterResolver::assignedLocation('seed-b', 1, $u, [1, 2, 3]), range(1, 50));

        $this->assertNotSame($a, $b);
    }
}
