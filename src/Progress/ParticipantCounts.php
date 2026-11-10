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

use Closure;
use Flarum\Api\Context;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use WeakMap;

/**
 * How many members have found at least one Part of each Hunt in the response. Each Hunt asks for
 * a deferred value; the serializer resolves them after the whole document is walked, so every
 * Hunt in it, primary or included, is counted by one grouped query.
 *
 * Keyed by the actor, which Flarum builds afresh for every request (see ActorProgress).
 */
class ParticipantCounts
{
    /**
     * @var WeakMap<User, array{pending: list<int>, counts: array<int, int>}>
     */
    protected WeakMap $memo;

    public function __construct(
        protected ConnectionInterface $db
    ) {
        $this->memo = new WeakMap();
    }

    /**
     * @return Closure(): int
     */
    public function count(Context $context, int $huntId): Closure
    {
        $actor = $context->getActor();
        $state = $this->memo[$actor] ?? ['pending' => [], 'counts' => []];

        if (! array_key_exists($huntId, $state['counts']) && ! in_array($huntId, $state['pending'], true)) {
            $state['pending'][] = $huntId;
        }

        $this->memo[$actor] = $state;

        return function () use ($actor, $huntId): int {
            $this->load($actor);

            return $this->memo[$actor]['counts'][$huntId] ?? 0;
        };
    }

    protected function load(User $actor): void
    {
        $state = $this->memo[$actor];

        if (! $state['pending']) {
            return;
        }

        $counts = $this->db->table('fof_scavenger_finds')
            ->join('fof_scavenger_parts', 'fof_scavenger_parts.id', '=', 'fof_scavenger_finds.part_id')
            ->whereIn('fof_scavenger_parts.hunt_id', $state['pending'])
            ->groupBy('fof_scavenger_parts.hunt_id')
            ->selectRaw('count(distinct '.$this->db->getQueryGrammar()->wrap('fof_scavenger_finds.user_id').') as participants')
            ->addSelect('fof_scavenger_parts.hunt_id')
            ->pluck('participants', 'hunt_id');

        foreach ($state['pending'] as $huntId) {
            $state['counts'][$huntId] = (int) ($counts[$huntId] ?? 0);
        }

        $state['pending'] = [];
        $this->memo[$actor] = $state;
    }
}
