<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt;

enum PlacementMode: string
{
    case Fixed = 'fixed';
    case Assigned = 'assigned';
    case Wandering = 'wandering';

    public function isRandom(): bool
    {
        return $this !== self::Fixed;
    }

    /**
     * Fixed Parts are shown before Random ones when a page has more candidates than it may show.
     */
    public function priority(): int
    {
        return match ($this) {
            self::Fixed => 0,
            self::Assigned => 1,
            self::Wandering => 2,
        };
    }
}
