<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Access;

use Flarum\User\User;
use FoF\ScavengerHunt\Permission;
use Illuminate\Database\Eloquent\Builder;

/**
 * Locations are the answer key: only Hunt managers may read them, by any route.
 */
class ScopeLocationVisibility
{
    public function __invoke(User $actor, Builder $query): void
    {
        if (! $actor->can(Permission::MANAGE)) {
            $query->whereRaw('1 = 0');
        }
    }
}
