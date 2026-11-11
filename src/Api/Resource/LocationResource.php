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
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use FoF\ScavengerHunt\Event;
use FoF\ScavengerHunt\HidingSpot\HidingSpotRegistry;
use FoF\ScavengerHunt\Location;
use FoF\ScavengerHunt\Permission;
use FoF\ScavengerHunt\Placement\LocationValidator;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * @extends AbstractDatabaseResource<Location>
 */
class LocationResource extends AbstractDatabaseResource
{
    public function __construct(
        protected HidingSpotRegistry $spots,
        protected LocationValidator $validator,
    ) {
    }

    public function type(): string
    {
        return 'scavenger-hunt-locations';
    }

    public function model(): string
    {
        return Location::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $query->whereVisibleTo($context->getActor());
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Create::make()
                ->authenticated()
                ->can(Permission::MANAGE),
            Endpoint\Update::make()
                ->authenticated()
                ->can(Permission::MANAGE),
            Endpoint\Delete::make()
                ->authenticated()
                ->can(Permission::MANAGE),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('route')
                ->nullable()
                ->writable()
                ->maxLength(100),
            Schema\Str::make('spot')
                ->requiredOnCreate()
                ->writable()
                ->maxLength(100),
            Schema\Str::make('contextType')
                ->nullable()
                ->writable()
                ->maxLength(30),
            Schema\Integer::make('contextId')
                ->nullable()
                ->writable()
                ->min(1),
            // False when the spot's extension has been disabled: the Location is kept but never matched.
            Schema\Boolean::make('isAvailable')
                ->get(fn (Location $location) => $this->spots->get($location->spot) !== null),

            Schema\Relationship\ToOne::make('part')
                ->type('scavenger-hunt-parts')
                ->includable()
                ->writableOnCreate()
                ->requiredOnCreate(),
        ];
    }

    public function saving(object $model, OriginalContext $context): ?object
    {
        $this->validator->validate($model);

        return $model;
    }

    public function created(object $model, OriginalContext $context): ?object
    {
        $this->events->dispatch(new Event\LocationCreated($model, $context->getActor()));

        return $model;
    }

    public function updated(object $model, OriginalContext $context): ?object
    {
        $this->events->dispatch(new Event\LocationUpdated($model, $context->getActor()));

        return $model;
    }

    public function deleted(object $model, OriginalContext $context): void
    {
        $this->events->dispatch(new Event\LocationDeleted($model, $context->getActor()));
    }
}
