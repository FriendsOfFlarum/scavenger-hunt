<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Placement;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\User\User;
use FoF\ScavengerHunt\HidingSpot\HidingSpotRegistry;

/**
 * Recently active content an audience can see, to hide Parts in. A fixed handful of queries,
 * whatever the number of Parts or discussions.
 */
class CandidatePool
{
    public const RECENT_DAYS = 90;
    public const MAX_DISCUSSIONS = 50;
    public const FIRST_POSTS = 20;
    public const MAX_USERS = 50;

    public function __construct(
        protected HidingSpotRegistry $spots,
    ) {
    }

    public function load(User $audience): Candidates
    {
        $since = Carbon::now()->subDays(self::RECENT_DAYS);

        $discussions = Discussion::query()
            ->whereVisibleTo($audience)
            ->where('discussions.last_posted_at', '>=', $since)
            ->orderByDesc('discussions.last_posted_at')
            ->orderByDesc('discussions.id')
            ->limit(self::MAX_DISCUSSIONS)
            ->get(['discussions.id', 'discussions.title', 'discussions.slug', 'discussions.last_posted_at'])
            ->all();

        if ($discussions === []) {
            return new Candidates([], [], [], $this->tags($audience));
        }

        $ids = array_map(fn (Discussion $discussion) => $discussion->id, $discussions);

        $posts = Post::query()
            ->whereVisibleTo($audience)
            ->whereIn('posts.discussion_id', $ids)
            ->where('posts.type', 'comment')
            ->where('posts.number', '<=', self::FIRST_POSTS)
            ->orderBy('posts.discussion_id')
            ->orderBy('posts.number')
            ->get(['posts.id', 'posts.type', 'posts.discussion_id', 'posts.number'])
            ->all();

        return new Candidates($discussions, $posts, $this->posters($audience, $ids, $since), $this->tags($audience));
    }

    /**
     * Members who commented in the discussions lately, when the audience may open profiles: a user
     * page is shown to anyone who can view the forum.
     *
     * @param list<int> $discussionIds
     * @return list<User>
     */
    protected function posters(User $audience, array $discussionIds, Carbon $since): array
    {
        if ($audience->cannot('viewForum')) {
            return [];
        }

        $recent = Post::query()
            ->whereVisibleTo($audience)
            ->whereIn('posts.discussion_id', $discussionIds)
            ->where('posts.type', 'comment')
            ->where('posts.created_at', '>=', $since)
            ->whereNotNull('posts.user_id')
            ->select('posts.user_id');

        return User::query()
            ->whereVisibleTo($audience)
            ->whereIn('users.id', $recent)
            ->orderByDesc('users.id')
            ->limit(self::MAX_USERS)
            ->get()
            ->all();
    }

    /**
     * The tags drawn as tiles on the tags page: top-level tags with a position.
     *
     * @return list<AbstractModel>
     */
    protected function tags(User $audience): array
    {
        $spot = $this->spots->get('tags.tile');
        $class = $spot?->context !== null ? $this->spots->contextModel($spot->context) : null;

        if ($class === null) {
            return [];
        }

        /** @var list<AbstractModel> */
        return $class::query()
            ->whereVisibleTo($audience)
            ->whereNotNull('position')
            ->whereNull('parent_id')
            ->orderBy('position')
            ->get()
            ->all();
    }
}
