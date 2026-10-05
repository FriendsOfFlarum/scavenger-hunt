<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Api\Resource;

use Carbon\Carbon;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractResource;
use Flarum\Api\Resource\Contracts\Countable;
use Flarum\Api\Resource\Contracts\Listable;
use Flarum\Api\Resource\Contracts\Paginatable;
use Flarum\Api\Schema;
use Flarum\User\User;
use FoF\ScavengerHunt\Permission;
use FoF\ScavengerHunt\Progress\ActorProgress;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Tobyz\JsonApiServer\Context as OriginalContext;
use Tobyz\JsonApiServer\Pagination\OffsetPagination;
use Tobyz\JsonApiServer\Schema\CustomFilter;
use Tobyz\JsonApiServer\Schema\CustomSort;

/**
 * A Hunt's Participants: members with at least one Find, with their progress and claim timing.
 * Computed from grouped queries, so this is not a database resource.
 */
class ParticipantResource extends AbstractResource implements Listable, Countable, Paginatable
{
    /**
     * Claims faster than this, three or more times, get flagged for a closer look.
     */
    public const FAST_CLAIM_MS = 1000;

    public const FAST_CLAIM_COUNT = 3;

    public function __construct(
        protected ConnectionInterface $db
    ) {
    }

    public function type(): string
    {
        return 'scavenger-hunt-participants';
    }

    public function getId(object $model, OriginalContext $context): string
    {
        return $model->hunt_id.':'.$model->user_id;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated()
                ->can(Permission::MANAGE)
                ->defaultSort('-foundCount')
                ->defaultInclude(['user'])
                ->paginate(20, 50),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Integer::make('foundCount'),
            Schema\DateTime::make('firstFoundAt'),
            Schema\DateTime::make('lastFoundAt'),
            Schema\DateTime::make('completedAt')->nullable(),
            Schema\Integer::make('position')->nullable(),
            Schema\Integer::make('encounterCount'),
            Schema\Integer::make('averageClaimMs')->nullable(),
            Schema\Boolean::make('fastClaimFlag'),
            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable()
                ->get(fn (object $participant) => $participant->user),
        ];
    }

    public function filters(): array
    {
        return [
            CustomFilter::make('hunt', function (object $query, string|array $value) {
                $query->huntId = (int) (is_array($value) ? reset($value) : $value);
            }),
            CustomFilter::make('q', function (object $query, string|array $value) {
                $query->search = trim((string) (is_array($value) ? reset($value) : $value));
            }),
        ];
    }

    public function sorts(): array
    {
        return [
            CustomSort::make('foundCount', fn (object $query, string $direction) => $query->sort = ['found_count', $direction]),
            CustomSort::make('lastFoundAt', fn (object $query, string $direction) => $query->sort = ['last_found_at', $direction]),
            CustomSort::make('completedAt', fn (object $query, string $direction) => $query->sort = ['completed_at', $direction]),
        ];
    }

    public function resolveSorts(): array
    {
        return $this->sorts();
    }

    public function query(OriginalContext $context): object
    {
        return (object) ['huntId' => null, 'search' => '', 'sort' => ['found_count', 'desc'], 'limit' => null, 'offset' => 0];
    }

    public function paginate(object $query, OffsetPagination $pagination): void
    {
        $query->limit = $pagination->limit;
        $query->offset = $pagination->offset;
    }

    public function results(object $query, OriginalContext $context): iterable
    {
        if (! $query->huntId) {
            return [];
        }

        [$column, $direction] = $query->sort;

        $rows = $this->base($query)
            ->orderBy($column, $direction)
            ->orderBy('fof_scavenger_finds.user_id')
            ->when($query->limit !== null, fn (Builder $q) => $q->limit($query->limit))
            ->offset($query->offset)
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $userIds = $rows->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $users = User::query()->with('groups')->whereIn('id', $userIds)->get()->keyBy('id');
        $timing = $this->timing((int) $query->huntId, $userIds);
        $positions = $this->positions((int) $query->huntId, $userIds);

        $participants = [];

        foreach ($rows as $row) {
            $userId = (int) $row->user_id;
            $claims = $timing[$userId] ?? null;

            $participants[] = (object) [
                'hunt_id' => $query->huntId,
                'user_id' => $userId,
                'user' => $users->get($userId),
                'foundCount' => (int) $row->found_count,
                'firstFoundAt' => Carbon::parse($row->first_found_at),
                'lastFoundAt' => Carbon::parse($row->last_found_at),
                'completedAt' => $row->completed_at ? Carbon::parse($row->completed_at) : null,
                'position' => $positions[$userId] ?? null,
                'encounterCount' => (int) ($claims->encounters ?? 0),
                'averageClaimMs' => isset($claims->average_claim_ms) ? (int) round((float) $claims->average_claim_ms) : null,
                'fastClaimFlag' => (int) ($claims->fast_claims ?? 0) >= self::FAST_CLAIM_COUNT,
            ];
        }

        return $participants;
    }

    public function count(object $query, OriginalContext $context): ?int
    {
        if (! $query->huntId) {
            return 0;
        }

        return $this->db->query()->fromSub($this->base($query), 'participants')->count();
    }

    protected function base(object $query): Builder
    {
        $wrap = fn (string $column) => $this->db->getQueryGrammar()->wrap($column);

        $base = $this->db->table('fof_scavenger_finds')
            ->join('fof_scavenger_parts', 'fof_scavenger_parts.id', '=', 'fof_scavenger_finds.part_id')
            ->leftJoin('fof_scavenger_completions', function ($join) use ($query) {
                $join->on('fof_scavenger_completions.user_id', '=', 'fof_scavenger_finds.user_id')
                    ->where('fof_scavenger_completions.hunt_id', '=', $query->huntId);
            })
            ->where('fof_scavenger_parts.hunt_id', $query->huntId)
            ->groupBy('fof_scavenger_finds.user_id')
            ->select('fof_scavenger_finds.user_id')
            ->selectRaw('count(*) as found_count')
            ->selectRaw('min('.$wrap('fof_scavenger_finds.found_at').') as first_found_at')
            ->selectRaw('max('.$wrap('fof_scavenger_finds.found_at').') as last_found_at')
            ->selectRaw('max('.$wrap('fof_scavenger_completions.completed_at').') as completed_at');

        if ($query->search !== '') {
            $base->join('users', 'users.id', '=', 'fof_scavenger_finds.user_id')
                ->where('users.username', 'like', addcslashes($query->search, '%_\\').'%');
        }

        return $base;
    }

    /**
     * @param list<int> $userIds
     * @return array<int, object>
     */
    protected function timing(int $huntId, array $userIds): array
    {
        $wrap = fn (string $column) => $this->db->getQueryGrammar()->wrap($column);

        return $this->db->table('fof_scavenger_encounters')
            ->join('fof_scavenger_parts', 'fof_scavenger_parts.id', '=', 'fof_scavenger_encounters.part_id')
            ->where('fof_scavenger_parts.hunt_id', $huntId)
            ->whereIn('fof_scavenger_encounters.user_id', $userIds)
            ->groupBy('fof_scavenger_encounters.user_id')
            ->select('fof_scavenger_encounters.user_id')
            ->selectRaw('count(*) as encounters')
            ->selectRaw('avg('.$wrap('fof_scavenger_encounters.claim_ms').') as average_claim_ms')
            ->selectRaw('sum(case when '.$wrap('fof_scavenger_encounters.claim_ms').' < '.self::FAST_CLAIM_MS.' then 1 else 0 end) as fast_claims')
            ->get()
            ->keyBy(fn (object $row) => (int) $row->user_id)
            ->all();
    }

    /**
     * @param list<int> $userIds
     * @return array<int, int>
     */
    protected function positions(int $huntId, array $userIds): array
    {
        return $this->db->table('fof_scavenger_completions as c')
            ->where('c.hunt_id', $huntId)
            ->whereIn('c.user_id', $userIds)
            ->select('c.user_id')
            ->selectSub(fn ($query) => ActorProgress::positionQuery($query), 'position')
            ->pluck('position', 'user_id')
            ->map(fn ($position) => (int) $position)
            ->all();
    }
}
