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

use Carbon\Carbon;
use FoF\ScavengerHunt\HidingSpot\HidingSpotRegistry;
use FoF\ScavengerHunt\Hunt;
use FoF\ScavengerHunt\Location;
use FoF\ScavengerHunt\Part;
use Illuminate\Contracts\Cache\Repository;

class HuntMapRepository
{
    /**
     * Versioned so a deploy that changes the map's shape never reads an old-shape copy.
     */
    public const CACHE_KEY = 'fof-scavenger-hunt.map.v1';

    public const GENERATION_KEY = 'fof-scavenger-hunt.map.generation';

    /**
     * What the forum payload needs on every page load, cached apart from the full map so that
     * seeds and Locations are never deserialized just to say which Hunts are running.
     */
    public const SUMMARY_KEY = 'fof-scavenger-hunt.summary.v1';

    /**
     * An upper bound on staleness for changes made outside the API (a direct DB edit, tinker).
     */
    protected const MAX_TTL = 3600;

    protected int $deferring = 0;

    protected bool $forgetPending = false;

    public function __construct(
        protected Repository $cache,
        protected HidingSpotRegistry $spots,
    ) {
    }

    public function get(): HuntMap
    {
        return $this->load()[0];
    }

    /**
     * @return list<array{id: int, slug: string, title: string, guests_can_see: bool, part_count: int}>
     */
    public function summary(): array
    {
        $generation = $this->generation();
        $cached = $this->cache->get(self::SUMMARY_KEY);

        if (is_array($cached) && ($cached['generation'] ?? null) === $generation) {
            return $cached['hunts'];
        }

        [$map, $expiresAt] = $this->load();

        $hunts = array_values(array_map(fn (array $hunt) => [
            'id' => $hunt['id'],
            'slug' => $hunt['slug'],
            'title' => $hunt['title'],
            'guests_can_see' => $hunt['guests_can_see'],
            'part_count' => $map->partCount($hunt['id']),
        ], $map->hunts));

        $this->cache->put(self::SUMMARY_KEY, ['generation' => $generation, 'hunts' => $hunts], max(1, $expiresAt - Carbon::now()->getTimestamp()));

        return $hunts;
    }

    public function forget(): void
    {
        if ($this->deferring > 0) {
            $this->forgetPending = true;

            return;
        }

        $this->cache->add(self::GENERATION_KEY, 0);
        $this->cache->increment(self::GENERATION_KEY);
        $this->cache->forget(self::CACHE_KEY);
        $this->cache->forget(self::SUMMARY_KEY);
    }

    /**
     * Runs a batch of changes, collecting the invalidations they cause into one at the end.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function forgetOnce(callable $callback): mixed
    {
        $this->deferring++;

        try {
            return $callback();
        } finally {
            $this->deferring--;

            if ($this->deferring === 0 && $this->forgetPending) {
                $this->forgetPending = false;
                $this->forget();
            }
        }
    }

    /**
     * @return array{0: HuntMap, 1: int} the map and when it stops being true (unix time)
     */
    protected function load(): array
    {
        $generation = $this->generation();
        $cached = $this->cache->get(self::CACHE_KEY);

        if (is_array($cached) && ($cached['generation'] ?? null) === $generation) {
            return [HuntMap::fromArray($cached['map']), (int) $cached['expires_at']];
        }

        $now = Carbon::now();
        [$map, $ttl] = $this->build($now);
        $expiresAt = $now->getTimestamp() + $ttl;

        // Stamped with the generation read before building: if a change lands mid-build, this copy
        // is already stale, and the next reader rebuilds instead of trusting it.
        $this->cache->put(self::CACHE_KEY, ['generation' => $generation, 'expires_at' => $expiresAt, 'map' => $map->toArray()], $ttl);

        return [$map, $expiresAt];
    }

    protected function generation(): int
    {
        return (int) $this->cache->get(self::GENERATION_KEY, 0);
    }

    /**
     * The map, and how long it stays true: until the next time an enabled Hunt starts or ends,
     * so scheduled Hunts begin and finish on time without anyone clearing a cache.
     *
     * @return array{0: HuntMap, 1: int}
     */
    public function build(Carbon $now): array
    {
        $ttl = self::MAX_TTL;
        $hunts = [];

        $enabled = Hunt::query()
            ->where('is_enabled', true)
            ->get(['id', 'slug', 'title', 'guests_can_see', 'seed', 'is_enabled', 'starts_at', 'ends_at']);

        foreach ($enabled as $hunt) {
            foreach ([$hunt->starts_at, $hunt->ends_at] as $boundary) {
                if ($boundary !== null && $boundary->gt($now)) {
                    $ttl = min($ttl, max(1, $boundary->getTimestamp() - $now->getTimestamp()));
                }
            }

            if ($hunt->isActive($now)) {
                $hunts[$hunt->id] = [
                    'id' => $hunt->id,
                    'slug' => $hunt->slug,
                    'title' => $hunt->title,
                    'guests_can_see' => $hunt->guests_can_see,
                    'seed' => $hunt->seed,
                ];
            }
        }

        $parts = [];
        $locations = [];

        if ($hunts !== []) {
            $rows = Part::query()
                ->whereIn('hunt_id', array_keys($hunts))
                ->orderBy('id')
                ->get(['id', 'hunt_id', 'mode', 'wander_chance', 'sort']);

            foreach ($rows as $part) {
                $parts[$part->id] = [
                    'id' => $part->id,
                    'hunt_id' => $part->hunt_id,
                    'mode' => $part->mode->value,
                    'wander_chance' => $part->wander_chance,
                    'sort' => $part->sort,
                ];
            }
        }

        if ($parts !== []) {
            $rows = Location::query()
                ->whereIn('part_id', array_keys($parts))
                ->orderBy('id')
                ->get(['id', 'part_id', 'route', 'spot', 'context_type', 'context_id']);

            foreach ($rows as $location) {
                if ($this->spots->get($location->spot) === null) {
                    continue;
                }

                $locations[$location->part_id][] = [
                    'id' => $location->id,
                    'part_id' => $location->part_id,
                    'route' => $location->route,
                    'spot' => $location->spot,
                    'context_type' => $location->context_type,
                    'context_id' => $location->context_id,
                ];
            }
        }

        return [new HuntMap($hunts, $parts, $locations), $ttl];
    }
}
