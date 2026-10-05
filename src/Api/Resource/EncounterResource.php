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
use Flarum\Api\Resource\AbstractResource;
use Flarum\Api\Schema;
use Flarum\Api\Serializer;
use FoF\ScavengerHunt\HidingSpot\HidingSpotRegistry;
use FoF\ScavengerHunt\HidingSpot\ReportedPage;
use FoF\ScavengerHunt\Lookup\EncounterResolver;
use FoF\ScavengerHunt\Lookup\ResolvedEncounter;
use Illuminate\Support\Arr;
use Tobyz\JsonApiServer\Context as OriginalContext;

use function Tobyz\JsonApiServer\json_api_response;

/**
 * Encounters are decided per request and never listed or fetched, so the only endpoint is the
 * lookup itself: report a page, get back whatever should be drawn on it.
 */
class EncounterResource extends AbstractResource
{
    public function __construct(
        protected EncounterResolver $resolver,
        protected HidingSpotRegistry $spots,
    ) {
    }

    public function type(): string
    {
        return 'scavenger-hunt-encounters';
    }

    public function getId(object $model, OriginalContext $context): string
    {
        /** @var ResolvedEncounter $model */
        return implode(':', [$model->part->id, $model->spot, $model->contextType ?? '', $model->contextId ?? '']);
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Endpoint::make('lookup')
                ->route('POST', '/')
                ->action(function (Context $context) {
                    $attributes = Arr::get($context->body(), 'data.attributes');

                    return $this->resolver->resolve(
                        $context->getActor(),
                        ReportedPage::fromRequest(is_array($attributes) ? $attributes : [], $this->spots, $context->getActor())
                    );
                })
                ->response(function (Context $context, array $encounters) {
                    $serializer = new Serializer($context);

                    foreach ($encounters as $encounter) {
                        $serializer->addPrimary($this, $encounter, ['part' => ['hunt' => []]]);
                    }

                    [$primary, $included] = $serializer->serialize();

                    return json_api_response(['data' => $primary, 'included' => $included]);
                }),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('spot'),
            Schema\Str::make('contextType')
                ->nullable(),
            Schema\Integer::make('contextId')
                ->nullable(),
            Schema\Str::make('token')
                ->nullable(),
            Schema\DateTime::make('expiresAt')
                ->nullable(),
            Schema\Relationship\ToOne::make('part')
                ->type('scavenger-hunt-parts')
                ->includable(),
        ];
    }
}
