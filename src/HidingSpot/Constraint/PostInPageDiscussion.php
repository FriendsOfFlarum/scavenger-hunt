<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\HidingSpot\Constraint;

use FoF\ScavengerHunt\HidingSpot\ReportedPage;
use Illuminate\Database\Eloquent\Builder;

/**
 * A reported post must belong to the discussion whose page it was reported on, which holds one
 * lookup to one discussion's posts.
 */
class PostInPageDiscussion
{
    public function __invoke(Builder $query, ReportedPage $page): void
    {
        $query->where('posts.discussion_id', $page->intParam('id') ?? 0);
    }
}
