<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Listener;

use Flarum\User\Event\GroupsChanged;
use FoF\ScavengerHunt\Event\HuntCompleted;
use Illuminate\Contracts\Events\Dispatcher;

class AwardRewardGroup
{
    public function __construct(
        protected Dispatcher $events
    ) {
    }

    public function handle(HuntCompleted $event): void
    {
        $groupId = $event->completion->hunt->reward_group_id;

        if (! $groupId) {
            return;
        }

        $user = $event->completion->user;
        $oldGroups = $user->groups()->get()->all();

        $user->groups()->syncWithoutDetaching([$groupId]);
        $user->unsetRelation('groups');

        // The same event a group edit through the API raises, so permission caches and other
        // listeners see the new membership.
        $this->events->dispatch(new GroupsChanged($user, $oldGroups));
    }
}
