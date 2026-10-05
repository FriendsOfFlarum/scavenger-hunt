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

final class HidingSpot
{
    /**
     * @param list<string>|null $routes forum route names; null for any. A trailing `*` matches by prefix.
     * @param string|null $context the context alias an item of this spot belongs to
     * @param class-string|null $constrain invokable `(Builder $query, ReportedPage $page): void` tying the item to the page
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?array $routes,
        public readonly ?string $context,
        public readonly ?string $constrain,
    ) {
    }

    public function allowsRoute(string $route): bool
    {
        if ($this->routes === null) {
            return true;
        }

        foreach ($this->routes as $pattern) {
            if ($pattern === $route || (str_ends_with($pattern, '*') && str_starts_with($route, substr($pattern, 0, -1)))) {
                return true;
            }
        }

        return false;
    }
}
