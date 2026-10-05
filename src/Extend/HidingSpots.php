<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Extend;

use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * Registers Hiding spots another extension renders with a `<HidingSpot>` component.
 *
 *     (new FoF\ScavengerHunt\Extend\HidingSpots())
 *         ->context('article', Article::class)
 *         ->add('acme-blog.article-footer', 'acme-blog.admin.scavenger.article_footer', ['blog.article'], 'article')
 *
 * Keys should be namespaced so two extensions cannot claim the same one.
 */
class HidingSpots implements ExtenderInterface
{
    /**
     * @var list<array{key: string, label: string, routes: list<string>|null, context: string|null, constrain: class-string|null}>
     */
    protected array $spots = [];

    /**
     * @var array<string, class-string<Model>>
     */
    protected array $contexts = [];

    /**
     * @var list<string>
     */
    protected array $excludedRoutes = [];

    /**
     * @var array<string, string>
     */
    protected array $routeAliases = [];

    /**
     * @param string $label translation key shown to Hunt managers
     * @param list<string>|null $routes forum route names the spot is drawn on; null for any. A trailing `*` matches by prefix.
     * @param string|null $context alias registered with context(), for spots drawn once per item (a post, a user)
     * @param class-string|null $constrain invokable `(Builder $query, ReportedPage $page): void` that ties a reported item to the page it was reported on
     */
    public function add(string $key, string $label, ?array $routes = null, ?string $context = null, ?string $constrain = null): self
    {
        $this->spots[] = compact('key', 'label', 'routes', 'context', 'constrain');

        return $this;
    }

    /**
     * @param class-string<Model> $model
     */
    public function context(string $alias, string $model): self
    {
        $this->contexts[$alias] = $model;

        return $this;
    }

    /**
     * Leave routes out of the page list Hunt managers pick from, e.g. feeds or confirmation pages.
     */
    public function excludeRoute(string ...$routes): self
    {
        array_push($this->excludedRoutes, ...$routes);

        return $this;
    }

    /**
     * Map a frontend-only route name to the forum route it renders, e.g. `discussion.near` to
     * `discussion`, so a Location saved for the forum route also matches the alias.
     */
    public function routeAlias(string $alias, string $route): self
    {
        $this->routeAliases[$alias] = $route;

        return $this;
    }

    public function extend(Container $container, ?Extension $extension = null): void
    {
        $container->extend('fof-scavenger-hunt.spots', fn (array $spots) => array_merge($spots, $this->spots));
        $container->extend('fof-scavenger-hunt.contexts', fn (array $contexts) => array_merge($contexts, $this->contexts));
        $container->extend('fof-scavenger-hunt.excluded-routes', fn (array $routes) => array_merge($routes, $this->excludedRoutes));
        $container->extend('fof-scavenger-hunt.route-aliases', fn (array $aliases) => array_merge($aliases, $this->routeAliases));
    }
}
