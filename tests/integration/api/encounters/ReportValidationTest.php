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

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Extend\HidingSpots;
use FoF\ScavengerHunt\Setting;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use FoF\ScavengerHunt\Tests\integration\PostInReportedDiscussion;
use PHPUnit\Framework\Attributes\Test;

class ReportValidationTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $this->extend(
            (new HidingSpots())->add('test.post', 'test', ['discussion'], 'post', PostInReportedDiscussion::class)
        );

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            Discussion::class => [
                ['id' => 1, 'title' => 'Snow talk', 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1],
                ['id' => 2, 'title' => 'Other talk', 'user_id' => 2, 'first_post_id' => 2, 'comment_count' => 1],
                ['id' => 3, 'title' => 'Hidden talk', 'user_id' => 1, 'first_post_id' => 3, 'comment_count' => 1, 'hidden_at' => Carbon::now()->toDateTimeString(), 'hidden_user_id' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>a</p></t>', 'number' => 1],
                ['id' => 2, 'discussion_id' => 2, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>b</p></t>', 'number' => 1],
                ['id' => 3, 'discussion_id' => 3, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>c</p></t>', 'number' => 1],
            ],
            'fof_scavenger_hunts' => [$this->huntRow()],
            'fof_scavenger_parts' => [
                $this->partRow(['id' => 1, 'name' => 'Any post part']),
                $this->partRow(['id' => 2, 'name' => 'Post two part', 'sort' => 1]),
            ],
            'fof_scavenger_locations' => [
                $this->locationRow(['id' => 1, 'part_id' => 1, 'route' => 'discussion', 'spot' => 'test.post', 'context_type' => 'post']),
                $this->locationRow(['id' => 2, 'part_id' => 2, 'route' => 'discussion', 'spot' => 'test.post', 'context_type' => 'post', 'context_id' => 2]),
            ],
        ]);
    }

    protected function postSpot(int $postId): array
    {
        return ['spot' => 'test.post', 'contextType' => 'post', 'contextId' => $postId];
    }

    #[Test]
    public function a_visible_post_on_its_own_discussion_page_matches(): void
    {
        $data = $this->body($this->lookup(2, 'discussion', [$this->postSpot(1)], ['id' => '1-snow-talk']))['data'];

        $this->assertCount(1, $data);
        $this->assertSame('1', $data[0]['relationships']['part']['data']['id']);
        $this->assertSame(1, $data[0]['attributes']['contextId']);
    }

    #[Test]
    public function a_post_reported_on_another_discussions_page_is_dropped(): void
    {
        $this->assertSame([], $this->body($this->lookup(2, 'discussion', [$this->postSpot(2)], ['id' => '1-snow-talk']))['data']);
    }

    #[Test]
    public function a_post_the_member_cannot_see_is_dropped(): void
    {
        $this->assertSame([], $this->body($this->lookup(2, 'discussion', [$this->postSpot(3)], ['id' => '3']))['data']);
    }

    #[Test]
    public function a_post_that_does_not_exist_is_dropped(): void
    {
        $this->assertSame([], $this->body($this->lookup(2, 'discussion', [$this->postSpot(999)], ['id' => '1']))['data']);
    }

    #[Test]
    public function a_specific_item_only_matches_itself(): void
    {
        $this->setting(Setting::MAX_PARTS_PER_PAGE, 5);

        $onOne = $this->body($this->lookup(2, 'discussion', [$this->postSpot(1)], ['id' => '1-snow-talk']))['data'];
        $this->assertSame(['1'], array_map(fn ($e) => $e['relationships']['part']['data']['id'], $onOne));

        $onTwo = $this->body($this->lookup(2, 'discussion', [$this->postSpot(2)], ['id' => '2-other-talk']))['data'];
        $this->assertSame(['1', '2'], array_map(fn ($e) => $e['relationships']['part']['data']['id'], $onTwo));
    }

    #[Test]
    public function unregistered_spots_and_wrong_routes_are_ignored(): void
    {
        $response = $this->lookup(2, 'index', [['spot' => 'nope'], $this->postSpot(1)]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([], $this->body($response)['data']);
    }
}
