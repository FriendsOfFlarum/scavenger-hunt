<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tracking;

use Carbon\Carbon;
use FoF\ScavengerHunt\Hunt;
use Illuminate\Database\ConnectionInterface;
use WeakMap;

/**
 * The dashboard's numbers for one Hunt, in a handful of grouped queries.
 *
 * "Shown" counts Encounters: every claimed one, plus unclaimed ones until they are pruned a week
 * after expiry. Guests' and managers' previews are never stored, so they don't count.
 *
 * @phpstan-type Stats array{participants: int, finds: int, completions: int, shown: int, parts: list<array{id: int, name: string, shown: int, found: int, averageClaimMs: int|null}>, completionsPerDay: list<array{date: string, count: int}>}
 */
class HuntStats
{
    /**
     * @var WeakMap<Hunt, Stats>
     */
    protected WeakMap $memo;

    public function __construct(
        protected ConnectionInterface $db
    ) {
        $this->memo = new WeakMap();
    }

    /**
     * @return Stats
     */
    public function for(Hunt $hunt): array
    {
        return $this->memo[$hunt] ??= $this->compute($hunt);
    }

    /**
     * @return Stats
     */
    protected function compute(Hunt $hunt): array
    {
        $parts = $this->db->table('fof_scavenger_parts')->where('hunt_id', $hunt->id)->orderBy('sort')->orderBy('id')->get(['id', 'name']);
        $partIds = $parts->pluck('id')->map(fn ($id) => (int) $id)->all();

        $finds = $this->db->table('fof_scavenger_finds')
            ->whereIn('part_id', $partIds)
            ->groupBy('part_id')
            ->select('part_id')
            ->selectRaw('count(*) as found')
            ->pluck('found', 'part_id');

        $grammar = $this->db->getQueryGrammar();
        $encounters = $this->db->table('fof_scavenger_encounters')
            ->whereIn('part_id', $partIds)
            ->groupBy('part_id')
            ->select('part_id')
            ->selectRaw('count(*) as shown')
            ->selectRaw('avg('.$grammar->wrap('claim_ms').') as average_claim_ms')
            ->get()
            ->keyBy('part_id');

        $completedAt = $this->db->table('fof_scavenger_completions')->where('hunt_id', $hunt->id)->orderBy('completed_at')->pluck('completed_at');
        $perDay = [];

        foreach ($completedAt as $time) {
            $day = Carbon::parse($time)->format('Y-m-d');
            $perDay[$day] = ($perDay[$day] ?? 0) + 1;
        }

        return [
            'participants' => (int) $this->db->table('fof_scavenger_finds')->whereIn('part_id', $partIds)->distinct()->count('user_id'),
            'finds' => (int) array_sum($finds->all()),
            'completions' => $completedAt->count(),
            'shown' => (int) $encounters->sum('shown'),
            'parts' => $parts->map(fn ($part) => [
                'id' => (int) $part->id,
                'name' => (string) $part->name,
                'shown' => (int) ($encounters[$part->id]->shown ?? 0),
                'found' => (int) ($finds[$part->id] ?? 0),
                'averageClaimMs' => isset($encounters[$part->id]) && $encounters[$part->id]->average_claim_ms !== null ? (int) round((float) $encounters[$part->id]->average_claim_ms) : null,
            ])->values()->all(),
            'completionsPerDay' => array_map(fn (string $date, int $count) => ['date' => $date, 'count' => $count], array_keys($perDay), $perDay),
        ];
    }
}
