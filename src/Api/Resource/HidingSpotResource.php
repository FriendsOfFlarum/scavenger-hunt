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
use Flarum\Api\Resource\Contracts\Listable;
use Flarum\Api\Schema;
use Flarum\Locale\TranslatorInterface;
use FoF\ScavengerHunt\HidingSpot\HidingSpot;
use FoF\ScavengerHunt\HidingSpot\HidingSpotRegistry;
use FoF\ScavengerHunt\Permission;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * The registered Hiding spots, with the forum's page routes as document meta, for the
 * dashboard's Location pickers. Routes follow whichever extensions are enabled.
 */
class HidingSpotResource extends AbstractResource implements Listable
{
    public function __construct(
        protected HidingSpotRegistry $spots,
        protected TranslatorInterface $translator,
    ) {
    }

    public function type(): string
    {
        return 'scavenger-hunt-spots';
    }

    public function getId(object $model, OriginalContext $context): string
    {
        /** @var HidingSpot $model */
        return $model->key;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated()
                ->can(Permission::MANAGE)
                ->meta([
                    Schema\Arr::make('routes')
                        ->get(fn () => $this->spots->pageRoutes()),
                    Schema\Arr::make('routeAliases')
                        ->get(fn () => (object) $this->spots->routeAliases()),
                ]),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('label')
                ->get(fn (HidingSpot $spot) => $this->translator->trans($spot->label)),
            Schema\Arr::make('routes')
                ->nullable()
                ->get(fn (HidingSpot $spot) => $spot->routes),
            Schema\Str::make('contextType')
                ->nullable()
                ->get(fn (HidingSpot $spot) => $spot->context),
        ];
    }

    public function query(OriginalContext $context): object
    {
        return (object) [];
    }

    public function results(object $query, OriginalContext $context): iterable
    {
        return array_values($this->spots->all());
    }

    public function filters(): array
    {
        return [];
    }

    public function sorts(): array
    {
        return [];
    }

    public function resolveSorts(): array
    {
        return [];
    }
}
