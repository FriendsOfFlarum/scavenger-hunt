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

use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractResource;
use Flarum\Api\Resource\Contracts\Findable;
use Flarum\Api\Schema;
use FoF\ScavengerHunt\Hunt;
use FoF\ScavengerHunt\Permission;
use FoF\ScavengerHunt\Tracking\HuntStats;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * A Hunt's numbers for its dashboard. The Hunt itself is the model, so authorization works on it.
 */
class StatsResource extends AbstractResource implements Findable
{
    public function __construct(
        protected HuntStats $stats
    ) {
    }

    public function type(): string
    {
        return 'scavenger-hunt-stats';
    }

    public function getId(object $model, OriginalContext $context): string
    {
        /** @var Hunt $model */
        return (string) $model->id;
    }

    public function find(string $id, OriginalContext $context): ?object
    {
        return Hunt::query()->whereVisibleTo($context->getActor())->find($id);
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make()
                ->authenticated()
                ->can(Permission::MANAGE),
        ];
    }

    public function fields(): array
    {
        $stat = fn (string $key) => fn (Hunt $hunt) => $this->stats->for($hunt)[$key];

        return [
            Schema\Integer::make('participants')->get($stat('participants')),
            Schema\Integer::make('finds')->get($stat('finds')),
            Schema\Integer::make('completions')->get($stat('completions')),
            Schema\Integer::make('shown')->get($stat('shown')),
            Schema\Arr::make('parts')->get($stat('parts')),
            Schema\Arr::make('completionsPerDay')->get($stat('completionsPerDay')),
        ];
    }
}
