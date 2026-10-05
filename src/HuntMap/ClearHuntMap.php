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

use Flarum\Extension\Event\Disabled;
use Flarum\Extension\Event\Enabled;
use FoF\ScavengerHunt\Event;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Subscribers are resolved on every boot, so the repository (and the spot registry behind it)
 * is only resolved when something actually changes.
 */
class ClearHuntMap
{
    public function __construct(
        protected Container $container
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
            // Enabling or disabling an extension can add or remove Hiding spots.
            Enabled::class,
            Disabled::class,
        ], [$this, 'clear']);
    }

    public function clear(): void
    {
        $this->container->make(HuntMapRepository::class)->forget();
    }
}
