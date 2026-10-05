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
use Psr\Http\Message\ServerRequestInterface;
use WeakMap;

/**
 * The actor's Finds and Completions, loaded once per request for every field that needs them.
 *
 * Resources are container singletons that serve many requests, so the memo is keyed by the
 * request object (FND-124): it dies with the request instead of leaking another actor's progress.
 */
class ActorProgress
{
    /**
     * @var WeakMap<ServerRequestInterface, array{found: array<int, Carbon>, huntCounts: array<int, int>, completed: array<int, Carbon>}>
     */
    protected WeakMap $memo;

    public function __construct(
        protected ConnectionInterface $db
    ) {
        $this->memo = new WeakMap();
    }

    public function isFound(Context $context, int $partId): bool
    {
        return isset($this->load($context)['found'][$partId]);
    }

    public function foundAt(Context $context, int $partId): ?Carbon
    {
        return $this->load($context)['found'][$partId] ?? null;
    }

    public function foundCount(Context $context, int $huntId): int
    {
        return $this->load($context)['huntCounts'][$huntId] ?? 0;
    }

    public function completedAt(Context $context, int $huntId): ?Carbon
    {
        return $this->load($context)['completed'][$huntId] ?? null;
    }

    /**
     * @return array{found: array<int, Carbon>, huntCounts: array<int, int>, completed: array<int, Carbon>}
     */
    protected function load(Context $context): array
    {
        $request = $context->request;

        if (isset($this->memo[$request])) {
            return $this->memo[$request];
        }

        $actor = $context->getActor();
        $progress = ['found' => [], 'huntCounts' => [], 'completed' => []];

        if (! $actor->isGuest()) {
            $finds = $this->db->table('fof_scavenger_finds')
                ->join('fof_scavenger_parts', 'fof_scavenger_parts.id', '=', 'fof_scavenger_finds.part_id')
                ->where('fof_scavenger_finds.user_id', $actor->id)
                ->get(['fof_scavenger_finds.part_id', 'fof_scavenger_finds.found_at', 'fof_scavenger_parts.hunt_id']);

            foreach ($finds as $find) {
                $progress['found'][(int) $find->part_id] = Carbon::parse($find->found_at);
                $progress['huntCounts'][(int) $find->hunt_id] = ($progress['huntCounts'][(int) $find->hunt_id] ?? 0) + 1;
            }

            $completions = $this->db->table('fof_scavenger_completions')
                ->where('user_id', $actor->id)
                ->get(['hunt_id', 'completed_at']);

            foreach ($completions as $completion) {
                $progress['completed'][(int) $completion->hunt_id] = Carbon::parse($completion->completed_at);
            }
        }

        return $this->memo[$request] = $progress;
    }
}
