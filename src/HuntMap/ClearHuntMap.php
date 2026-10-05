<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\HuntMap;

use FoF\ScavengerHunt\Event;
use Illuminate\Contracts\Events\Dispatcher;

class ClearHuntMap
{
    public function __construct(
        protected HuntMapRepository $maps
    ) {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen([
            Event\HuntCreated::class,
            Event\HuntUpdated::class,
            Event\HuntDeleted::class,
            Event\PartCreated::class,
            Event\PartUpdated::class,
            Event\PartDeleted::class,
            Event\LocationCreated::class,
            Event\LocationUpdated::class,
            Event\LocationDeleted::class,
        ], [$this, 'clear']);
    }

    public function clear(): void
    {
        $this->maps->forget();
    }
}
