<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\HidingSpot;

final class ReportedSpot
{
    public function __construct(
        public readonly string $spot,
        public readonly ?string $contextType,
        public readonly ?int $contextId,
    ) {
    }
}
