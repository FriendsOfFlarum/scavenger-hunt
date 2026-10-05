<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Progress;

use Carbon\Carbon;
use Flarum\Api\Context;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Flarum\User\User;
use WeakMap;

/**
 * The actor's Finds and Completions for the Hunts in the current response, loaded once per
 * request. Endpoints scope it to their Hunts up front so a whole page loads in one go; asking
 * about a Hunt outside the scope widens it and reloads.
 *
 * Resources are container singletons that serve many requests, so state is keyed by the actor
 * object, which Flarum builds afresh for every request (FND-124's per-request rule). The request
 * object itself is not a stable key: the serializer works with derived copies of it.
 *
 * @phpstan-type Progress array{hunts: list<int>, found: array<int, Carbon>, huntCounts: array<int, int>, completed: array<int, array{at: Carbon, position: int}>}
 */
class ActorProgress
{
    /**
     * @var WeakMap<User, list<int>>
     */
    protected WeakMap $scopes;

    /**
     * @var WeakMap<User, Progress>
     */
    protected WeakMap $memo;

    public function __construct(
        protected ConnectionInterface $db
    ) {
        $this->scopes = new WeakMap();
        $this->memo = new WeakMap();
    }

    /**
     * @param iterable<int|string> $huntIds
     */
    public function scope(Context $context, iterable $huntIds): void
    {
        $actor = $context->getActor();
        $scope = $this->scopes[$actor] ?? [];

        foreach ($huntIds as $huntId) {
            if (! in_array((int) $huntId, $scope, true)) {
                $scope[] = (int) $huntId;
            }
        }

        $this->scopes[$actor] = $scope;
    }

    public function isFound(Context $context, int $huntId, int $partId): bool
    {
        return isset($this->load($context, $huntId)['found'][$partId]);
    }

    public function foundAt(Context $context, int $huntId, int $partId): ?Carbon
    {
        return $this->load($context, $huntId)['found'][$partId] ?? null;
    }

    public function foundCount(Context $context, int $huntId): int
    {
        return $this->load($context, $huntId)['huntCounts'][$huntId] ?? 0;
    }

    public function completedAt(Context $context, int $huntId): ?Carbon
    {
        return $this->load($context, $huntId)['completed'][$huntId]['at'] ?? null;
    }

    /**
     * The actor's place among the Hunt's finishers, or null if they haven't finished it.
     */
    public function position(Context $context, int $huntId): ?int
    {
        return $this->load($context, $huntId)['completed'][$huntId]['position'] ?? null;
    }

    /**
     * @return Progress
     */
    protected function load(Context $context, int $huntId): array
    {
        $actor = $context->getActor();

        $this->scope($context, [$huntId]);
        $scope = $this->scopes[$actor];

        if (isset($this->memo[$actor]) && $this->memo[$actor]['hunts'] === $scope) {
            return $this->memo[$actor];
        }

        $progress = ['hunts' => $scope, 'found' => [], 'huntCounts' => [], 'completed' => []];

        if (! $actor->isGuest()) {
            $finds = $this->db->table('fof_scavenger_finds')
                ->join('fof_scavenger_parts', 'fof_scavenger_parts.id', '=', 'fof_scavenger_finds.part_id')
                ->where('fof_scavenger_finds.user_id', $actor->id)
                ->whereIn('fof_scavenger_parts.hunt_id', $scope)
                ->get(['fof_scavenger_finds.part_id', 'fof_scavenger_finds.found_at', 'fof_scavenger_parts.hunt_id']);

            foreach ($finds as $find) {
                $progress['found'][(int) $find->part_id] = Carbon::parse($find->found_at);
                $progress['huntCounts'][(int) $find->hunt_id] = ($progress['huntCounts'][(int) $find->hunt_id] ?? 0) + 1;
            }

            $completions = $this->db->table('fof_scavenger_completions as c')
                ->where('c.user_id', $actor->id)
                ->whereIn('c.hunt_id', $scope)
                ->select('c.hunt_id', 'c.completed_at')
                ->selectSub(fn (Builder $query) => self::positionQuery($query), 'position')
                ->get();

            foreach ($completions as $completion) {
                $progress['completed'][(int) $completion->hunt_id] = [
                    'at' => Carbon::parse($completion->completed_at),
                    'position' => (int) $completion->position,
                ];
            }
        }

        return $this->memo[$actor] = $progress;
    }

    /**
     * A correlated subquery counting the Completions of the same Hunt earned up to and including
     * the outer row `c`: its finishing position. Portable, and served by (hunt_id, completed_at).
     */
    public static function positionQuery(Builder $query): Builder
    {
        return $query->from('fof_scavenger_completions as c2')
            ->selectRaw('count(*)')
            ->whereColumn('c2.hunt_id', 'c.hunt_id')
            ->where(function (Builder $query) {
                $query->whereColumn('c2.completed_at', '<', 'c.completed_at')
                    ->orWhere(function (Builder $query) {
                        $query->whereColumn('c2.completed_at', 'c.completed_at')->whereColumn('c2.id', '<=', 'c.id');
                    });
            });
    }
}
