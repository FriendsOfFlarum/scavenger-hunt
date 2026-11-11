<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration;

use Carbon\Carbon;

/**
 * Recently active discussions and posts to hide Parts in.
 */
trait SeedsForumContent
{
    protected function discussionRow(int $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'title' => "Discussion $id",
            'slug' => "discussion-$id",
            'user_id' => 2,
            'first_post_id' => $id,
            'comment_count' => 1,
            'created_at' => Carbon::now()->subDays(2)->toDateTimeString(),
            'last_posted_at' => Carbon::now()->subDays(1)->addMinutes($id)->toDateTimeString(),
        ], $overrides);
    }

    protected function postRow(int $id, int $discussionId, int $number, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'discussion_id' => $discussionId,
            'number' => $number,
            'user_id' => 2,
            'type' => 'comment',
            'content' => '<t><p>Hello</p></t>',
            'created_at' => Carbon::now()->subDays(1)->toDateTimeString(),
        ], $overrides);
    }
}
