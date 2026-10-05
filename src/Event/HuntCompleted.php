<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Event;

use Flarum\User\User;
use FoF\ScavengerHunt\Completion;

class HuntCompleted
{
    public function __construct(
        public readonly Completion $completion,
        public readonly User $actor
    ) {
    }
}
