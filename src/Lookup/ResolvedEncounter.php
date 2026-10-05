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

use Carbon\Carbon;
use FoF\ScavengerHunt\Part;

/**
 * A Part to draw in a reported spot. `token` is null for actors who may see but not claim.
 */
final class ResolvedEncounter
{
    public function __construct(
        public readonly Part $part,
        public readonly string $spot,
        public readonly ?string $contextType,
        public readonly ?int $contextId,
        public readonly ?string $token,
        public readonly ?Carbon $expiresAt,
    ) {
    }
}
