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

use Closure;
use Flarum\Http\RouteCollection;
use Illuminate\Database\Eloquent\Model;

class HidingSpotRegistry
{
    /**
     * @var array<string, HidingSpot>
     */
    protected array $spots = [];

    /**
     * @var list<array{name: string, path: string}>|null
     */
    protected ?array $pageRoutes = null;

    /**
     * @param list<array{key: string, label: string, routes: list<string>|null, context: string|null, constrain: class-string|null}> $definitions
     * @param array<string, class-string<Model>> $contexts
     * @param list<string> $excludedRoutes
     * @param Closure(): RouteCollection $forumRoutes resolved lazily: building the forum routes is only needed by the spots endpoint and Location validation
     * @param (Closure(string): void)|null $reportInvalid receives a message for each skipped definition
     */
    public function __construct(
        array $definitions,
        protected array $contexts,
        protected array $excludedRoutes,
        protected Closure $forumRoutes,
        protected ?Closure $reportInvalid = null,
    ) {
        // A bad registration from another extension is skipped, never thrown: the registry is
        // resolved on every lookup, and throwing would take the whole forum down with it.
        foreach ($definitions as $definition) {
            if (isset($this->spots[$definition['key']])) {
                $this->invalid("Hiding spot [{$definition['key']}] is registered twice; the first registration is kept.");

                continue;
            }

            if ($definition['context'] !== null && ! isset($contexts[$definition['context']])) {
                $this->invalid("Hiding spot [{$definition['key']}] uses the unregistered context [{$definition['context']}] and is ignored.");

                continue;
            }

            $this->spots[$definition['key']] = new HidingSpot(...$definition);
        }
    }

    protected function invalid(string $message): void
    {
        if ($this->reportInvalid) {
            ($this->reportInvalid)($message);
        }
    }

    public function get(string $key): ?HidingSpot
    {
        return $this->spots[$key] ?? null;
    }

    /**
     * @return array<string, HidingSpot>
     */
    public function all(): array
    {
        return $this->spots;
    }

    /**
     * @return class-string<Model>|null
     */
    public function contextModel(string $alias): ?string
    {
        return $this->contexts[$alias] ?? null;
    }

    /**
     * Forum pages a member can visit, so admins can pick them for a Location.
     *
     * @return list<array{name: string, path: string}>
     */
    public function pageRoutes(): array
    {
        if ($this->pageRoutes !== null) {
            return $this->pageRoutes;
        }

        $routes = [];

        foreach (($this->forumRoutes)()->getRoutes() as $name => $route) {
            if ($route['method'] === 'GET' && ! in_array($name, $this->excludedRoutes, true)) {
                $routes[] = ['name' => (string) $name, 'path' => $route['path']];
            }
        }

        usort($routes, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $this->pageRoutes = $routes;
    }

    public function isPageRoute(string $name): bool
    {
        return in_array($name, array_column($this->pageRoutes(), 'name'), true);
    }
}
