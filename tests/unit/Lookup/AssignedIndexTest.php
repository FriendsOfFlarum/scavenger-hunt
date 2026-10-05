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

class AssignedIndexTest extends TestCase
{
    #[Test]
    public function the_same_member_always_gets_the_same_location(): void
    {
        $first = EncounterResolver::assignedIndex('seed', 7, 42, 5);

        for ($i = 0; $i < 10; $i++) {
            $this->assertSame($first, EncounterResolver::assignedIndex('seed', 7, 42, 5));
        }
    }

    #[Test]
    public function members_are_spread_evenly_across_locations(): void
    {
        $buckets = array_fill(0, 4, 0);

        for ($userId = 1; $userId <= 4000; $userId++) {
            $buckets[EncounterResolver::assignedIndex(str_repeat('ab', 32), 3, $userId, 4)]++;
        }

        foreach ($buckets as $count) {
            $this->assertGreaterThan(850, $count);
            $this->assertLessThan(1150, $count);
        }
    }

    #[Test]
    public function a_different_seed_reshuffles_the_assignment(): void
    {
        $a = array_map(fn (int $u) => EncounterResolver::assignedIndex('seed-a', 1, $u, 3), range(1, 50));
        $b = array_map(fn (int $u) => EncounterResolver::assignedIndex('seed-b', 1, $u, 3), range(1, 50));

        $this->assertNotSame($a, $b);
    }
}
