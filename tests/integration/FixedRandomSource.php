<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration;

use FoF\ScavengerHunt\Lookup\RandomSource;

class FixedRandomSource implements RandomSource
{
    public function __construct(
        public int $value
    ) {
    }

    public function between(int $min, int $max): int
    {
        return max($min, min($max, $this->value));
    }
}
