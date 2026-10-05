<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\api\encounters;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Lookup\EncounterResolver;
use FoF\ScavengerHunt\Lookup\RandomSource;
use FoF\ScavengerHunt\Tests\integration\FixedRandomSource;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\Test;

class AssignedAndWanderingTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected const SEED = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    protected FixedRandomSource $random;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->secondMember()],
            'fof_scavenger_hunts' => [$this->huntRow(['seed' => self::SEED])],
            'fof_scavenger_parts' => [
                $this->partRow(['id' => 1, 'name' => 'Assigned hat', 'mode' => 'assigned']),
                $this->partRow(['id' => 2, 'name' => 'Wandering scarf', 'mode' => 'wandering', 'wander_chance' => 30, 'sort' => 1]),
            ],
            'fof_scavenger_locations' => [
                $this->locationRow(['id' => 1, 'part_id' => 1, 'route' => 'index', 'spot' => 'page.corner']),
                $this->locationRow(['id' => 2, 'part_id' => 1, 'route' => 'tags', 'spot' => 'page.corner']),
                $this->locationRow(['id' => 3, 'part_id' => 2, 'route' => 'index', 'spot' => 'page.footer']),
                $this->locationRow(['id' => 4, 'part_id' => 2, 'route' => 'tags', 'spot' => 'page.footer']),
            ],
        ]);

        $this->random = new FixedRandomSource(100);
        $this->app()->getContainer()->instance(RandomSource::class, $this->random);
    }

    protected function partIds(?int $userId, string $route, string $spot): array
    {
        return array_map(
            fn (array $encounter) => $encounter['relationships']['part']['data']['id'],
            $this->body($this->lookup($userId, $route, [['spot' => $spot]]))['data']
        );
    }

    #[Test]
    public function an_assigned_part_appears_only_at_each_members_own_location(): void
    {
        foreach ([2, 4] as $userId) {
            $mine = ['index', 'tags'][EncounterResolver::assignedIndex(self::SEED, 1, $userId, 2)];
            $other = $mine === 'index' ? 'tags' : 'index';

            $this->assertSame(['1'], $this->partIds($userId, $mine, 'page.corner'), "user $userId at $mine");
            $this->assertSame(['1'], $this->partIds($userId, $mine, 'page.corner'), "user $userId stays put");
            $this->assertSame([], $this->partIds($userId, $other, 'page.corner'), "user $userId at $other");
        }
    }

    #[Test]
    public function guests_never_see_assigned_parts(): void
    {
        $this->assertSame([], $this->partIds(null, 'index', 'page.corner'));
        $this->assertSame([], $this->partIds(null, 'tags', 'page.corner'));
    }

    #[Test]
    public function a_wandering_part_appears_when_the_roll_is_within_its_chance(): void
    {
        $this->random->value = 31;
        $this->assertSame([], $this->partIds(2, 'index', 'page.footer'));

        $this->random->value = 30;
        $this->assertSame(['2'], $this->partIds(2, 'index', 'page.footer'));
    }

    #[Test]
    public function an_open_wandering_encounter_is_reused_rather_than_rerolled(): void
    {
        $this->random->value = 1;
        $this->assertSame(['2'], $this->partIds(2, 'index', 'page.footer'));

        $this->random->value = 100;
        $this->assertSame(['2'], $this->partIds(2, 'index', 'page.footer'));
        $this->assertSame(1, $this->database()->table('fof_scavenger_encounters')->where('part_id', 2)->count());
    }

    #[Test]
    public function a_wandering_part_open_elsewhere_does_not_appear_twice(): void
    {
        $this->random->value = 1;
        $this->assertSame(['2'], $this->partIds(2, 'index', 'page.footer'));
        $this->assertSame([], $this->partIds(2, 'tags', 'page.footer'));
    }
}
