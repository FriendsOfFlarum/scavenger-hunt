<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\placement;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Extend;
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\ScavengerHunt\Hunt;
use FoF\ScavengerHunt\Placement\Candidates;
use FoF\ScavengerHunt\Placement\CandidatePool;
use FoF\ScavengerHunt\Placement\HuntAudience;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use FoF\ScavengerHunt\Tests\integration\SeedsForumContent;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\Test;

class CandidatePoolTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;
    use SeedsForumContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'fof-scavenger-hunt');

        // Stands in for flarum/approval, which isn't installed here: any extension's visibility
        // scope applies to the audience, so an unapproved discussion is skipped the same way.
        $this->extend(
            (new Extend\ModelVisibility(Discussion::class))
                ->scope(fn (User $actor, Builder $query) => $query->where('discussions.id', '!=', 5))
        );

        $old = Carbon::now()->subDays(100)->toDateTimeString();

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                $this->secondMember(),
                ['id' => 5, 'username' => 'oldtimer', 'email' => 'oldtimer@machine.local', 'is_email_confirmed' => 1],
            ],
            Discussion::class => [
                $this->discussionRow(1, ['title' => 'Public chat']),
                $this->discussionRow(2, ['is_private' => true]),
                $this->discussionRow(3, ['hidden_at' => Carbon::now()->toDateTimeString()]),
                $this->discussionRow(4, ['last_posted_at' => $old]),
                $this->discussionRow(5),
                $this->discussionRow(6, ['title' => 'Staff room']),
                // Hidden, and its author's account is gone: never visible to the transient member.
                $this->discussionRow(7, ['user_id' => null, 'hidden_at' => Carbon::now()->toDateTimeString()]),
            ],
            Post::class => [
                $this->postRow(1, 1, 1),
                $this->postRow(2, 1, 2, ['user_id' => 4, 'type' => 'discussionRenamed', 'content' => '["a","b"]']),
                $this->postRow(3, 1, 3, ['hidden_at' => Carbon::now()->toDateTimeString()]),
                $this->postRow(4, 1, 4, ['user_id' => 4]),
                $this->postRow(5, 1, 21),
                $this->postRow(6, 1, 5, ['user_id' => 5, 'created_at' => $old]),
                $this->postRow(10, 2, 1),
                $this->postRow(11, 3, 1),
                $this->postRow(12, 4, 1, ['user_id' => 5, 'created_at' => $old]),
                $this->postRow(13, 5, 1),
                $this->postRow(14, 6, 1),
                $this->postRow(15, 7, 1, ['user_id' => null]),
            ],
            Tag::class => [
                ['id' => 1, 'name' => 'General', 'slug' => 'general', 'position' => 0],
                ['id' => 2, 'name' => 'Staff', 'slug' => 'staff', 'position' => 1, 'is_restricted' => true],
                ['id' => 3, 'name' => 'Child', 'slug' => 'child', 'position' => 0, 'parent_id' => 1],
                ['id' => 4, 'name' => 'Cloud', 'slug' => 'cloud', 'position' => null],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 6, 'tag_id' => 2],
            ],
            'group_permission' => [
                ['group_id' => Group::MEMBER_ID, 'permission' => 'tag2.viewForum'],
            ],
        ]);
    }

    protected function load(bool $guestsCanSee): Candidates
    {
        $container = $this->app()->getContainer();
        $hunt = new Hunt();
        $hunt->guests_can_see = $guestsCanSee;

        return $container->make(CandidatePool::class)->load($container->make(HuntAudience::class)->for($hunt));
    }

    /**
     * @param list<object> $models
     * @return list<int>
     */
    protected function ids(array $models): array
    {
        return array_map(fn ($model) => (int) $model->getKey(), $models);
    }

    #[Test]
    public function guests_get_recent_public_content_newest_first(): void
    {
        $pool = $this->load(true);

        $this->assertSame([1], $this->ids($pool->discussions));
        $this->assertSame([1, 4, 6], $this->ids($pool->posts), 'comment posts among the first 20, not hidden');
        $this->assertSame([4, 2], $this->ids($pool->users), 'members who commented in the last 90 days');
        $this->assertSame([1], $this->ids($pool->tags), 'top-level tags with a tile, not restricted');
    }

    #[Test]
    public function a_members_only_hunt_uses_what_members_can_see(): void
    {
        $pool = $this->load(false);

        $this->assertSame([6, 1], $this->ids($pool->discussions));
        $this->assertSame([1, 4, 6, 14], $this->ids($pool->posts));
        $this->assertSame([1, 2], $this->ids($pool->tags));
    }

    #[Test]
    public function nobody_is_proposed_when_the_audience_cannot_view_the_forum(): void
    {
        $this->app();
        $this->database()->table('group_permission')->where('permission', 'viewForum')->delete();
        $this->database()->table('group_permission')->insert(['group_id' => Group::MEMBER_ID, 'permission' => 'viewForum']);

        $guests = $this->load(true);
        $this->assertSame([], $guests->discussions);
        $this->assertSame([], $guests->users);

        $members = $this->load(false);
        $this->assertSame([6, 1], $this->ids($members->discussions));
        $this->assertSame([4, 2], $this->ids($members->users));
    }
}
