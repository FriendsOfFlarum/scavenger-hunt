<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Template;

final class TemplatePart
{
    /**
     * @param array{x: int|float, y: int|float, width: int|float, z: int} $layer
     */
    public function __construct(
        public readonly string $key,
        public readonly string $image,
        public readonly array $layer,
    ) {
    }
}
