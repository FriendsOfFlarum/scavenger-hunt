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

use Flarum\Notification\NotificationSyncer;
use FoF\ScavengerHunt\Event\HuntCompleted;
use FoF\ScavengerHunt\Notification\HuntCompletedBlueprint;

class SendCompletionNotification
{
    public function __construct(
        protected NotificationSyncer $notifications
    ) {
    }

    public function handle(HuntCompleted $event): void
    {
        $this->notifications->sync(new HuntCompletedBlueprint($event->completion->hunt), [$event->completion->user]);
    }
}
