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
 * The reported discussion must be the one whose page it was reported on.
 */
class IsPageDiscussion
{
    public function __invoke(Builder $query, ReportedPage $page): void
    {
        $query->where('discussions.id', $page->intParam('id') ?? 0);
    }
}
