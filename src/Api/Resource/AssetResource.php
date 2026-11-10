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
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use FoF\ScavengerHunt\Asset;
use FoF\ScavengerHunt\Event\AssetDeleted;
use FoF\ScavengerHunt\Image\AssetUsage;
use FoF\ScavengerHunt\Permission;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Contracts\Filesystem\Factory;
use Psr\Log\LoggerInterface;
use Throwable;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * Uploaded images. Hunt managers list them to pick from; only admins rename and delete them.
 * Uploads go through UploadAssetController, since they are multipart rather than JSON:API.
 *
 * @extends AbstractDatabaseResource<Asset>
 */
class AssetResource extends AbstractDatabaseResource
{
    public function __construct(
        protected AssetUsage $usage,
        protected Factory $filesystem,
        protected TranslatorInterface $translator,
        protected LoggerInterface $log,
    ) {
    }

    public function type(): string
    {
        return 'scavenger-hunt-assets';
    }

    public function model(): string
    {
        return Asset::class;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated()
                ->can(Permission::MANAGE)
                ->defaultSort('-createdAt')
                ->paginate(),
            Endpoint\Update::make()
                ->authenticated()
                ->admin(),
            Endpoint\Delete::make()
                ->authenticated()
                ->admin(),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')
                ->writable()
                // min:1 alone lets an empty string through; Laravel skips rules for empty values.
                ->rule('filled')
                ->maxLength(100),
            Schema\Str::make('url')
                ->get(fn (Asset $asset) => $this->disk()->url($asset->path)),
            Schema\Str::make('mimeType'),
            Schema\Integer::make('width')
                ->nullable(),
            Schema\Integer::make('height')
                ->nullable(),
            Schema\Integer::make('size'),
            Schema\DateTime::make('createdAt'),
            Schema\Boolean::make('isInUse')
                ->get(fn (Asset $asset, Context $context) => $this->usage->isInUse($context, $asset->id)),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt'),
        ];
    }

    public function deleting(object $model, OriginalContext $context): void
    {
        if ($this->usage->inUse([$model->id])) {
            throw new ValidationException(['asset' => $this->translator->trans('fof-scavenger-hunt.api.asset_in_use')]);
        }
    }

    public function deleted(object $model, OriginalContext $context): void
    {
        $this->events->dispatch(new AssetDeleted($model, $context->getActor()));

        // The row is already gone, so a storage failure leaves an orphaned file rather than a failed request.
        try {
            $this->disk()->delete($model->path);
        } catch (Throwable $e) {
            $this->log->warning("[fof/scavenger-hunt] Could not delete the file of deleted Asset {$model->id} at {$model->path}: {$e->getMessage()}");
        }
    }

    protected function disk(): Cloud
    {
        /** @var Cloud $disk */
        $disk = $this->filesystem->disk('flarum-assets');

        return $disk;
    }
}
