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
 * A Completion is as visible as its Hunt: First finishers and Trophies are public once the Hunt is.
 */
class ScopeCompletionVisibility
{
    public function __invoke(User $actor, Builder $query): void
    {
        if ($actor->can(Permission::MANAGE)) {
            return;
        }

        $query->whereHas('hunt', function (Builder $hunts) use ($actor) {
            $hunts->whereVisibleTo($actor);
        });
    }
}
