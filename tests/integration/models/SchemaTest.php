<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\models;

use Carbon\Carbon;
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\ScavengerHunt\Hunt;
use FoF\ScavengerHunt\Permission;
use FoF\ScavengerHunt\PlacementMode;
use PHPUnit\Framework\Attributes\Test;

class SchemaTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $now = Carbon::now()->toDateTimeString();

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            'fof_scavenger_hunts' => [
                ['id' => 1, 'slug' => 'snowman', 'title' => 'Snowman', 'is_enabled' => true, 'guests_can_see' => true, 'seed' => str_repeat('a', 64), 'created_at' => $now, 'updated_at' => $now],
            ],
            'fof_scavenger_parts' => [
                ['id' => 1, 'hunt_id' => 1, 'name' => 'Nose', 'mode' => 'fixed', 'sort' => 0, 'layer' => json_encode(['x' => 10, 'y' => 20, 'width' => 30, 'z' => 1]), 'created_at' => $now, 'updated_at' => $now],
                ['id' => 2, 'hunt_id' => 1, 'name' => 'Hat', 'mode' => 'assigned', 'sort' => 1, 'created_at' => $now, 'updated_at' => $now],
            ],
            'fof_scavenger_locations' => [
                ['id' => 1, 'part_id' => 1, 'spot' => 'page.corner', 'created_at' => $now, 'updated_at' => $now],
            ],
            'fof_scavenger_encounters' => [
                ['id' => 1, 'part_id' => 1, 'user_id' => 2, 'location_id' => 1, 'token_hash' => str_repeat('b', 64), 'created_at' => $now, 'expires_at' => $now, 'issued_at_ms' => 0],
            ],
            'fof_scavenger_finds' => [
                ['id' => 1, 'part_id' => 1, 'user_id' => 2, 'found_at' => $now],
            ],
            'fof_scavenger_completions' => [
                ['id' => 1, 'hunt_id' => 1, 'user_id' => 2, 'completed_at' => $now],
            ],
        ]);
    }

    #[Test]
    public function models_relate_and_cast(): void
    {
        $this->app();

        $hunt = Hunt::query()->findOrFail(1);

        $this->assertTrue($hunt->is_enabled);
        $this->assertCount(2, $hunt->parts);
        $this->assertSame(PlacementMode::Fixed, $hunt->parts[0]->mode);
        $this->assertSame(PlacementMode::Assigned, $hunt->parts[1]->mode);
        $this->assertTrue($hunt->parts[1]->mode->isRandom());
        $this->assertEquals(['x' => 10, 'y' => 20, 'width' => 30, 'z' => 1], $hunt->parts[0]->layer);
        $this->assertSame('page.corner', $hunt->parts[0]->locations[0]->spot);
        $this->assertSame(2, $hunt->parts[0]->finds[0]->user_id);

        $member = User::query()->findOrFail(2);
        $this->assertCount(1, $member->scavengerFinds);
        $this->assertCount(1, $member->scavengerCompletions);
    }

    #[Test]
    public function deleting_a_hunt_cascades_to_everything_beneath_it(): void
    {
        $this->app();

        if ($this->database()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('flarum/testing turns foreign keys off on SQLite, so nothing cascades.');
        }

        Hunt::query()->findOrFail(1)->delete();

        foreach (['parts', 'locations', 'encounters', 'finds', 'completions'] as $table) {
            $this->assertSame(0, $this->database()->table("fof_scavenger_$table")->count(), $table);
        }
    }

    #[Test]
    public function the_schedule_decides_whether_a_hunt_is_active(): void
    {
        $this->app();

        $now = Carbon::parse('2026-12-01 12:00:00');

        $hunt = new Hunt();
        $hunt->is_enabled = true;
        $this->assertTrue($hunt->isActive($now));

        $hunt->starts_at = $now->copy()->addMinute();
        $this->assertFalse($hunt->isActive($now));
        $this->assertFalse($hunt->hasStarted($now));

        $hunt->starts_at = $now->copy()->subMinute();
        $hunt->ends_at = $now->copy();
        $this->assertFalse($hunt->isActive($now), 'ends_at is exclusive');
        $this->assertTrue($hunt->hasStarted($now));

        $hunt->ends_at = null;
        $hunt->is_enabled = false;
        $this->assertFalse($hunt->isActive($now));
    }

    #[Test]
    public function permissions_are_seeded_for_members_and_moderators(): void
    {
        $this->app();

        $this->assertTrue($this->database()->table('group_permission')->where(['group_id' => Group::MEMBER_ID, 'permission' => Permission::PLAY])->exists());
        $this->assertTrue($this->database()->table('group_permission')->where(['group_id' => Group::MODERATOR_ID, 'permission' => Permission::MANAGE])->exists());
    }
}
