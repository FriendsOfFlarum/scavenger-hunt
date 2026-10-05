<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Gamification;

use FoF\Gamification\Leaderboard\MetricInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

/**
 * Scavenger Hunts completed.
 */
class HuntsCompleted implements MetricInterface
{
    public const KEY = 'scavenger-hunt.hunts-completed';

    public function __construct(
        protected ConnectionInterface $connection
    ) {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'fof-scavenger-hunt.gamification.metric.hunts_completed';
    }

    public function supportsPeriods(): bool
    {
        return true;
    }

    public function query(?\DateTimeInterface $since, ?\DateTimeInterface $until = null): Builder
    {
        return $this->window(
            $this->connection->table('fof_scavenger_completions')
                ->groupBy('fof_scavenger_completions.user_id')
                ->select('fof_scavenger_completions.user_id as user_id')
                ->selectRaw('COUNT(*) as score'),
            $since,
            $until
        );
    }

    public function activityQuery(?\DateTimeInterface $since, ?\DateTimeInterface $until = null): ?Builder
    {
        // Raw identifiers don't get the table prefix applied, so it is added by hand (gamification #165).
        return $this->window(
            $this->connection->table('fof_scavenger_completions')
                ->select('fof_scavenger_completions.user_id as user_id')
                ->selectRaw($this->connection->getTablePrefix().'fof_scavenger_completions.completed_at as happened_at'),
            $since,
            $until
        );
    }

    protected function window(Builder $query, ?\DateTimeInterface $since, ?\DateTimeInterface $until): Builder
    {
        if ($since !== null) {
            $query->where('fof_scavenger_completions.completed_at', '>=', $since);
        }

        if ($until !== null) {
            $query->where('fof_scavenger_completions.completed_at', '<', $until);
        }

        return $query;
    }
}
