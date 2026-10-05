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
use Flarum\Api\Serializer;
use FoF\ScavengerHunt\Access\HuntAccess;
use FoF\ScavengerHunt\Claim\ClaimService;
use FoF\ScavengerHunt\Find;
use FoF\ScavengerHunt\Progress\ActorProgress;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Tobyz\JsonApiServer\Context as OriginalContext;

use function Tobyz\JsonApiServer\json_api_response;

/**
 * @extends AbstractDatabaseResource<Find>
 */
class FindResource extends AbstractDatabaseResource
{
    public function __construct(
        protected ClaimService $claims,
        protected HuntAccess $access,
        protected ActorProgress $progress,
    ) {
    }

    public function type(): string
    {
        return 'scavenger-hunt-finds';
    }

    public function model(): string
    {
        return Find::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $actor = $context->getActor();

        if (! $this->access->canManage($actor)) {
            $query->where('fof_scavenger_finds.user_id', $actor->id);
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Endpoint::make('claim')
                ->route('POST', '/')
                ->authenticated()
                ->action(function (Context $context) {
                    $token = Arr::get($context->body(), 'data.attributes.token');

                    return $this->claims->claim($context->getActor(), is_string($token) ? $token : '');
                })
                ->response(function (Context $context, Find $find) {
                    $this->progress->scope($context, [$find->part->hunt_id]);

                    $serializer = new Serializer($context);
                    $serializer->addPrimary($this, $find, ['part' => ['hunt' => []]]);

                    [$primary, $included] = $serializer->serialize();

                    return json_api_response(['data' => $primary[0], 'included' => $included])->withStatus(201);
                }),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\DateTime::make('foundAt'),
            Schema\Relationship\ToOne::make('part')
                ->type('scavenger-hunt-parts')
                ->includable(),
            Schema\Relationship\ToOne::make('user')
                ->type('users')
                ->includable(),
        ];
    }
}
