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

use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
use FoF\ScavengerHunt\Placement\HidingPlacePicker;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * `POST /api/scavenger-hunts/{id}/hiding-suggestions` `{parts: [id], exclude: [{spot, route?, contextType, contextId}]}`:
 * proposed hiding places for the listed Parts, saving nothing.
 */
class SuggestHidingPlacesController implements RequestHandlerInterface
{
    use LoadsManagedHunt;

    public function __construct(
        protected HidingPlacePicker $picker,
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
            'parts.*' => ['integer', 'distinct'],
            'exclude' => ['sometimes', 'array'],
            'exclude.*' => ['array'],
            'exclude.*.spot' => ['required', 'string'],
            'exclude.*.route' => ['nullable', 'string'],
            'exclude.*.contextType' => ['nullable', 'string'],
            'exclude.*.contextId' => ['nullable', 'integer'],
        ])->validate();

        $parts = $this->partsOfHunt($hunt, array_map(intval(...), $body['parts']));

        $exclude = array_values(array_map(fn (array $place) => [
            'spot' => (string) $place['spot'],
            'route' => isset($place['route']) ? (string) $place['route'] : null,
            'contextType' => isset($place['contextType']) ? (string) $place['contextType'] : null,
            'contextId' => isset($place['contextId']) ? (int) $place['contextId'] : null,
        ], Arr::get($body, 'exclude', [])));

        return new JsonResponse(['data' => $this->picker->suggest($hunt, $parts, $exclude)]);
    }
}
