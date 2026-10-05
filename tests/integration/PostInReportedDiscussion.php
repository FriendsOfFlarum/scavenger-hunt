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

use FoF\ScavengerHunt\HidingSpot\ReportedPage;
use Illuminate\Database\Eloquent\Builder;

/**
 * A test stand-in for milestone 2's built-in post constraint.
 */
class PostInReportedDiscussion
{
    public function __invoke(Builder $query, ReportedPage $page): void
    {
        $query->where('posts.discussion_id', $page->intParam('id') ?? 0);
    }
}
