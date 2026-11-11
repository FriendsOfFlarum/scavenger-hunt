<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\api\forum;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\HuntMap\HuntMapRepository;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use Illuminate\Contracts\Cache\Repository;
use PHPUnit\Framework\Attributes\Test;

class ForumPayloadTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->moderatorUser()],
            'group_user' => [$this->moderatorMembership()],
            'fof_scavenger_hunts' => [
                $this->huntRow(['id' => 1]),
                $this->huntRow(['id' => 2, 'slug' => 'members-only', 'title' => 'Members only', 'guests_can_see' => false]),
                $this->huntRow(['id' => 3, 'slug' => 'empty', 'title' => 'No parts yet']),
                $this->huntRow(['id' => 4, 'slug' => 'disabled', 'is_enabled' => false]),
            ],
            'fof_scavenger_parts' => [
                $this->partRow(['id' => 1, 'hunt_id' => 1]),
                $this->partRow(['id' => 2, 'hunt_id' => 1, 'sort' => 1]),
                $this->partRow(['id' => 3, 'hunt_id' => 2]),
                $this->partRow(['id' => 4, 'hunt_id' => 4]),
            ],
            'fof_scavenger_locations' => [$this->locationRow()],
            'fof_scavenger_finds' => [['part_id' => 1, 'user_id' => 2, 'found_at' => Carbon::now()->toDateTimeString()]],
        ]);
    }

    protected function forum(?int $userId): array
    {
        $response = $this->send($this->request('GET', '/api', $userId ? ['authenticatedAs' => $userId] : []));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringNotContainsString(str_repeat('5eed', 16), (string) $response->getBody());
        $this->assertStringNotContainsString('page.corner', (string) $response->getBody());

        return $this->body($response)['data']['attributes'];
    }

    #[Test]
    public function members_get_their_progress_in_visible_active_hunts(): void
    {
        $attributes = $this->forum(2);

        $this->assertSame([
            ['id' => 1, 'slug' => 'snowman', 'title' => 'Build a Snowman', 'partCount' => 2, 'foundCount' => 1, 'canClaim' => true],
            ['id' => 2, 'slug' => 'members-only', 'title' => 'Members only', 'partCount' => 1, 'foundCount' => 0, 'canClaim' => true],
        ], $attributes['scavengerHunts']);
        $this->assertFalse($attributes['scavengerHuntCanManage']);
        $this->assertTrue($attributes['fof-scavenger-hunt.showHeaderIndicator']);
    }

    #[Test]
    public function guests_only_get_hunts_open_to_guests(): void
    {
        $this->assertSame(['snowman'], array_column($this->forum(null)['scavengerHunts'], 'slug'));
    }

    #[Test]
    public function hunt_managers_can_manage_but_not_claim_by_default(): void
    {
        $attributes = $this->forum(3);

        $this->assertTrue($attributes['scavengerHuntCanManage']);
        $this->assertFalse($attributes['scavengerHunts'][0]['canClaim']);
        $this->assertFalse($attributes['scavengerHuntCanAwardGroups'], 'reward groups follow core\'s user.editGroups');
        $this->assertTrue($this->forum(1)['scavengerHuntCanAwardGroups']);
        $this->assertTrue($attributes['scavengerHuntCanUploadImages'], 'moderators can upload images by default');
    }

    #[Test]
    public function only_hunt_managers_are_told_whether_they_can_upload_images(): void
    {
        $this->assertArrayNotHasKey('scavengerHuntCanUploadImages', $this->forum(2));
        $this->assertArrayNotHasKey('scavengerHuntCanUploadImages', $this->forum(null));
        $this->assertTrue($this->forum(1)['scavengerHuntCanUploadImages']);
    }

    #[Test]
    public function the_payload_is_built_from_a_slim_summary_without_secrets(): void
    {
        $this->forum(2);

        $summary = $this->app()->getContainer()->make(Repository::class)->get(HuntMapRepository::SUMMARY_KEY);

        $this->assertIsArray($summary);
        $this->assertSame(['snowman', 'members-only', 'empty'], array_column($summary['hunts'], 'slug'));
        $this->assertSame([2, 1, 0], array_column($summary['hunts'], 'part_count'));
        $this->assertArrayNotHasKey('seed', $summary['hunts'][0]);
    }
}
