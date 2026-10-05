<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Search\Find;

use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use FoF\ScavengerHunt\Find;
use FoF\ScavengerHunt\Permission;
use Illuminate\Database\Eloquent\Builder;

class FindSearcher extends AbstractSearcher
{
    public function getQuery(User $actor): Builder
    {
        $query = Find::query()->select('fof_scavenger_finds.*');

        if (! $actor->can(Permission::MANAGE)) {
            $query->where('fof_scavenger_finds.user_id', $actor->id);
        }

        return $query;
    }
}
