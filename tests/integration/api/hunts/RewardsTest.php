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
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\Event\GroupsChanged;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

class RewardsTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->moderatorUser(), $this->secondMember()],
            'group_user' => [$this->moderatorMembership()],
            'groups' => [['id' => 5, 'name_singular' => 'Snowman Builder', 'name_plural' => 'Snowman Builders', 'color' => '#7fb8d9', 'icon' => 'fas fa-snowman', 'is_hidden' => 0]],
            'fof_scavenger_hunts' => [$this->huntRow()],
            'fof_scavenger_parts' => [$this->partRow(['id' => 1])],
            'fof_scavenger_locations' => [$this->locationRow()],
        ]);
    }

    protected function updateHunt(int $userId, array $data): ResponseInterface
    {
        return $this->send($this->request('PATCH', '/api/scavenger-hunts/1', [
            'authenticatedAs' => $userId,
            'json' => ['data' => ['type' => 'scavenger-hunts', 'id' => '1'] + $data],
        ]));
    }

    protected function hunt(?int $userId): array
    {
        return $this->body($this->send($this->request('GET', '/api/scavenger-hunts/1', $userId ? ['authenticatedAs' => $userId] : [])))['data'];
    }

    protected function configureRewards(): void
    {
        $response = $this->updateHunt(3, [
            'attributes' => ['rewardMessage' => 'Use code **SNOW26** for 10% off!'],
            'relationships' => ['rewardGroup' => ['data' => ['type' => 'groups', 'id' => '5']]],
        ]);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function a_hunt_manager_can_set_a_reward_group_and_message(): void
    {
        $this->configureRewards();

        $row = $this->database()->table('fof_scavenger_hunts')->find(1);
        $this->assertSame(5, (int) $row->reward_group_id);
        $this->assertStringStartsWith('<', $row->reward_message, 'stored as TextFormatter XML');

        $hunt = $this->hunt(3);
        $this->assertSame('Use code **SNOW26** for 10% off!', $hunt['attributes']['rewardMessage']);
        $this->assertStringContainsString('SNOW26', $hunt['attributes']['rewardMessageHtml']);
        $this->assertSame('5', $hunt['relationships']['rewardGroup']['data']['id']);
    }

    #[Test]
    public function the_admin_and_guest_groups_cannot_be_a_reward(): void
    {
        foreach ([Group::ADMINISTRATOR_ID, Group::GUEST_ID] as $groupId) {
            $response = $this->updateHunt(3, ['relationships' => ['rewardGroup' => ['data' => ['type' => 'groups', 'id' => (string) $groupId]]]]);

            $this->assertEquals(422, $response->getStatusCode(), "group $groupId");
        }
    }

    #[Test]
    public function the_reward_message_is_only_revealed_to_finishers_and_managers(): void
    {
        $this->configureRewards();

        $this->assertArrayNotHasKey('rewardMessageHtml', $this->hunt(2)['attributes']);
        $this->assertArrayNotHasKey('rewardMessageHtml', $this->hunt(null)['attributes']);
        $this->assertArrayNotHasKey('rewardMessage', $this->hunt(2)['attributes']);

        $this->database()->table('fof_scavenger_completions')->insert(['hunt_id' => 1, 'user_id' => 2, 'completed_at' => Carbon::now()]);

        $this->assertStringContainsString('SNOW26', $this->hunt(2)['attributes']['rewardMessageHtml']);
        $this->assertArrayNotHasKey('rewardMessageHtml', $this->hunt(4)['attributes']);
    }

    #[Test]
    public function completing_a_hunt_awards_the_group_notifies_and_reports_the_position(): void
    {
        $this->configureRewards();

        $changed = [];
        $this->app()->getContainer()->make(Dispatcher::class)->listen(GroupsChanged::class, function (GroupsChanged $event) use (&$changed) {
            $changed[] = $event;
        });

        $token = $this->body($this->lookup(2, 'index', [['spot' => 'page.corner']]))['data'][0]['attributes']['token'];
        $response = $this->claim(2, $token);

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $hunt = collect($this->body($response)['included'])->firstWhere('type', 'scavenger-hunts');
        $this->assertSame(1, $hunt['attributes']['finishingPosition']);
        $this->assertStringContainsString('SNOW26', $hunt['attributes']['rewardMessageHtml']);

        $this->assertTrue($this->database()->table('group_user')->where(['user_id' => 2, 'group_id' => 5])->exists());
        $this->assertCount(1, $changed);
        $this->assertSame(2, $changed[0]->user->id);

        $notification = $this->database()->table('notifications')->where(['user_id' => 2, 'type' => 'scavengerHuntCompleted'])->first();
        $this->assertNotNull($notification);
        $this->assertSame(1, (int) $notification->subject_id);
    }
}
