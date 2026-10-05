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

use Carbon\Carbon;
use Flarum\User\User;
use FoF\ScavengerHunt\Permission;
use Illuminate\Database\Eloquent\Builder;

class ScopeHuntVisibility
{
    public function __invoke(User $actor, Builder $query): void
    {
        if ($actor->can(Permission::MANAGE)) {
            return;
        }

        $query->where('fof_scavenger_hunts.is_enabled', true)
            ->where(function (Builder $query) {
                $query->whereNull('fof_scavenger_hunts.starts_at')
                    ->orWhere('fof_scavenger_hunts.starts_at', '<=', Carbon::now());
            });

        if ($actor->isGuest()) {
            $query->where('fof_scavenger_hunts.guests_can_see', true);
        }
    }
}
