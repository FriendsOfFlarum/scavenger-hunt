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

use Flarum\User\User;

/**
 * What the browser says is on its page. Untrusted: it is only ever used to narrow what the
 * server would show anyway, and every item it names is re-checked before it counts.
 */
final class ReportedPage
{
    public const MAX_SPOTS = 50;

    /**
     * About one page of posts or discussions; more than that from one view is a probe.
     */
    public const MAX_ITEMS_PER_KIND = 20;

    protected const MAX_PARAMS = 10;

    /**
     * @param array<string, string> $params
     * @param list<ReportedSpot> $spots
     */
    public function __construct(
        public readonly string $route,
        public readonly array $params,
        public readonly array $spots,
        public readonly ?User $actor = null,
    ) {
    }

    /**
     * Anything malformed, unregistered, not allowed on the route, duplicated or over the caps is
     * dropped rather than rejected, so a probe learns nothing from error responses.
     *
     * @param array<string, mixed> $attributes
     */
    public static function fromRequest(array $attributes, HidingSpotRegistry $registry, ?User $actor = null): self
    {
        $route = is_string($attributes['route'] ?? null) ? $registry->canonicalRoute(substr($attributes['route'], 0, 100)) : '';

        $params = [];

        foreach (is_array($attributes['routeParams'] ?? null) ? $attributes['routeParams'] : [] as $key => $value) {
            if (is_string($key) && is_scalar($value) && count($params) < self::MAX_PARAMS) {
                $params[$key] = substr((string) $value, 0, 200);
            }
        }

        $spots = [];
        $seen = [];
        $items = [];
        $entries = is_array($attributes['spots'] ?? null) ? array_slice($attributes['spots'], 0, self::MAX_SPOTS) : [];

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! is_string($entry['spot'] ?? null) || $route === '') {
                continue;
            }

            $spot = $registry->get($entry['spot']);

            if ($spot === null || ! $spot->allowsRoute($route)) {
                continue;
            }

            $contextId = null;

            if ($spot->context === null) {
                if (isset($entry['contextType']) || isset($entry['contextId'])) {
                    continue;
                }
            } else {
                $contextId = filter_var($entry['contextId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

                if (($entry['contextType'] ?? null) !== $spot->context || $contextId === false) {
                    continue;
                }

                if (! isset($items[$spot->context][$contextId]) && count($items[$spot->context] ?? []) >= self::MAX_ITEMS_PER_KIND) {
                    continue;
                }

                $items[$spot->context][$contextId] = true;
            }

            $key = $spot->key.'|'.$contextId;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $spots[] = new ReportedSpot($spot->key, $spot->context, $contextId);
        }

        return new self($route, $params, $spots, $actor);
    }

    /**
     * The id at the start of a route parameter, e.g. 123 for the discussion parameter "123-snow-talk".
     */
    public function intParam(string $name): ?int
    {
        return preg_match('/^(\d+)/', $this->params[$name] ?? '', $matches) ? (int) $matches[1] : null;
    }
}
