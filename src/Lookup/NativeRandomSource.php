<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Lookup;

class NativeRandomSource implements RandomSource
{
    public function between(int $min, int $max): int
    {
        return random_int($min, $max);
    }
}
