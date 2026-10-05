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

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use FoF\ScavengerHunt\Completion;
use FoF\ScavengerHunt\Hunt;
use FoF\ScavengerHunt\Progress\ActorProgress;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * First finishers (`filter[hunt]`) and Trophies (`filter[user]`).
 *
 * @extends AbstractDatabaseResource<Completion>
 */
class CompletionResource extends AbstractDatabaseResource
{
    public function __construct(
        protected ConnectionInterface $db,
        protected ActorProgress $progress,
    ) {
    }

    public function type(): string
    {
        return 'scavenger-hunt-completions';
    }

    public function model(): string
    {
        return Completion::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $query->whereVisibleTo($context->getActor());
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->defaultSort('completedAt')
                ->paginate(20, 50)
                ->eagerLoad(['user', 'user.groups'])
                ->eagerLoadWhenIncluded(['hunt.parts' => ['hunt.parts']])
                ->beforeSerialization(fn (Context $context, array $results) => $this->prepare($context, $results['models'])),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\DateTime::make('completedAt'),
            Schema\Integer::make('position')
                ->get(fn (Completion $completion) => (int) $completion->getAttribute('position')),
            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToOne::make('hunt')
                ->type('scavenger-hunts')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('completedAt'),
        ];
    }

    /**
     * Positions for the whole page in one query, and the included Hunts' part counts and the
     * actor's progress loaded up front, so a long Trophy list stays a handful of queries.
     *
     * @param iterable<Completion> $models
     */
    protected function prepare(Context $context, iterable $models): void
    {
        $completions = new Collection(is_array($models) ? $models : iterator_to_array($models));

        if ($completions->isEmpty()) {
            return;
        }

        $positions = $this->db->table('fof_scavenger_completions as c')
            ->whereIn('c.id', $completions->modelKeys())
            ->select('c.id')
            ->selectSub(fn ($query) => ActorProgress::positionQuery($query), 'position')
            ->pluck('position', 'id');

        foreach ($completions as $completion) {
            $completion->setAttribute('position', (int) ($positions[$completion->id] ?? 0));
        }

        $completions->loadMissing('hunt');

        $hunts = new Collection($completions->pluck('hunt')->filter()->unique('id')->values()->all());
        $hunts->loadCount('parts');

        /** @var Hunt $hunt */
        foreach ($hunts as $hunt) {
            $hunt->setAttribute('part_count', (int) $hunt->getAttribute('parts_count'));
        }

        $this->progress->scope($context, $hunts->modelKeys());
    }
}
