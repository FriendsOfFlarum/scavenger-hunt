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

use Flarum\Api\Context;
use Flarum\Api\JsonApi;
use Flarum\Api\Serializer;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Locale\TranslatorInterface;
use FoF\ScavengerHunt\Api\Resource\AssetResource;
use FoF\ScavengerHunt\Image\AssetUploader;
use FoF\ScavengerHunt\Permission;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function Tobyz\JsonApiServer\json_api_response;

/**
 * `POST /api/scavenger-hunt/assets`: a multipart upload (field `file`) by someone allowed to upload images, answered
 * with the new `scavenger-hunt-assets` resource.
 */
class UploadAssetController implements RequestHandlerInterface
{
    public function __construct(
        protected AssetUploader $uploader,
        protected JsonApi $api,
        protected TranslatorInterface $translator,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $actor->assertCan(Permission::UPLOAD_IMAGES);

        $file = Arr::get($request->getUploadedFiles(), 'file');

        if (! $file instanceof UploadedFileInterface) {
            throw new ValidationException(['file' => $this->translator->trans('fof-scavenger-hunt.api.asset_file_missing')]);
        }

        $asset = $this->uploader->upload($file, $actor);

        /** @var AssetResource $resource */
        $resource = $this->api->getCollection(AssetResource::class);
        $serializer = new Serializer((new Context($this->api, $request))->withCollection($resource)->withResource($resource));
        $serializer->addPrimary($resource, $asset, []);

        [$primary] = $serializer->serialize();

        return json_api_response(['data' => $primary[0]])->withStatus(201);
    }
}
