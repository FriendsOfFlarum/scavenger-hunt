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
use FoF\ScavengerHunt\Find;

class PartFound
{
    public function __construct(
        public readonly Find $find,
        public readonly User $actor
    ) {
    }
}
