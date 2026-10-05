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

use Flarum\Foundation\AbstractServiceProvider;
use FoF\ScavengerHunt\HidingSpot\HidingSpotRegistry;
use FoF\ScavengerHunt\Lookup\NativeRandomSource;
use FoF\ScavengerHunt\Lookup\RandomSource;
use Illuminate\Contracts\Container\Container;
use Psr\Log\LoggerInterface;

/**
 * Hiding spots are a registry other extensions add to, and no core extender builds container
 * registries, so the empty lists and the registry itself are bound here.
 */
class ScavengerHuntServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton('fof-scavenger-hunt.spots', fn () => []);
        $this->container->singleton('fof-scavenger-hunt.contexts', fn () => []);
        $this->container->singleton('fof-scavenger-hunt.excluded-routes', fn () => []);
        $this->container->singleton('fof-scavenger-hunt.route-aliases', fn () => []);

        $this->container->singleton(HidingSpotRegistry::class, fn (Container $container) => new HidingSpotRegistry(
            $container->make('fof-scavenger-hunt.spots'),
            $container->make('fof-scavenger-hunt.contexts'),
            $container->make('fof-scavenger-hunt.excluded-routes'),
            fn () => $container->make('flarum.forum.routes'),
            fn (string $message) => $container->make(LoggerInterface::class)->warning("[fof/scavenger-hunt] $message"),
            $container->make('fof-scavenger-hunt.route-aliases'),
        ));

        $this->container->bind(RandomSource::class, NativeRandomSource::class);
    }
}
