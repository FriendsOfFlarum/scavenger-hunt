<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Api\Controller;

use Flarum\Api\JsonApi;
use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
use FoF\ScavengerHunt\Api\Resource\HuntResource;
use FoF\ScavengerHunt\Placement\HidingPlaceWriter;
use Illuminate\Contracts\Validation\Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /api/scavenger-hunts/{id}/hiding-places` `{parts: [{id, locations: [{spot, route, contextType, contextId}]}]}`:
 * replaces the Locations of the listed Parts in one go, answered with the Hunt document the
 * dashboard loads (Parts and their Locations included).
 */
class ApplyHidingPlacesController implements RequestHandlerInterface
{
    use LoadsManagedHunt;

    public const INCLUDE = 'parts,parts.locations,rewardGroup';

    public function __construct(
        protected HidingPlaceWriter $writer,
        protected JsonApi $api,
        protected Factory $validation,
        protected TranslatorInterface $translator,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $hunt = $this->managedHunt($request, $actor);

        $body = (array) $request->getParsedBody();

        $this->validation->make($body, [
            'parts' => ['required', 'array'],
            'parts.*' => ['array'],
            'parts.*.id' => ['required', 'integer', 'distinct'],
            'parts.*.locations' => ['present', 'array'],
            'parts.*.locations.*' => ['array'],
            'parts.*.locations.*.spot' => ['required', 'string', 'max:100'],
            'parts.*.locations.*.route' => ['nullable', 'string', 'max:100'],
            'parts.*.locations.*.contextType' => ['nullable', 'string', 'max:30'],
            'parts.*.locations.*.contextId' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $entries = array_values($body['parts']);
        $parts = $this->partsOfHunt($hunt, array_map(fn (array $entry) => (int) $entry['id'], $entries));

        $replacements = [];

        foreach ($entries as $i => $entry) {
            $replacements[] = [
                'part' => $parts[$i],
                'locations' => array_values(array_map(fn (array $location) => [
                    'spot' => (string) $location['spot'],
                    'route' => isset($location['route']) ? (string) $location['route'] : null,
                    'contextType' => isset($location['contextType']) ? (string) $location['contextType'] : null,
                    'contextId' => isset($location['contextId']) ? (int) $location['contextId'] : null,
                ], $entry['locations'])),
            ];
        }

        $this->writer->replace($replacements, $actor);

        // The Hunt's Show endpoint answers, so the document matches the dashboard's own load.
        $uri = $request->getUri();
        $huntPath = substr($uri->getPath(), 0, (int) strrpos($uri->getPath(), '/'));

        return $this->api
            ->forResource(HuntResource::class)
            ->forEndpoint('show')
            ->handle(
                $request
                    ->withMethod('GET')
                    ->withUri($uri->withPath($huntPath)->withQuery(''))
                    ->withQueryParams(['include' => self::INCLUDE])
                    ->withParsedBody(null)
            );
    }
}
