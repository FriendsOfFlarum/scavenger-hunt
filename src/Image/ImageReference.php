<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Image;

use Flarum\Api\Context;
use Flarum\User\User;
use FoF\ScavengerHunt\Asset;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Contracts\Filesystem\Factory;
use WeakMap;

/**
 * Hunts and Parts store images as references rather than URLs, so a change of
 * assets host or CDN applies without touching the database:
 *
 *   template:<template-key>/<file>.svg  — shipped in assets/templates, published on enable
 *   asset:<id>                          — an uploaded Asset
 *
 * The Asset library is a small, admin-curated table, so the first Asset URL a request needs loads
 * every Asset's path in one query. Keyed by the actor, which Flarum builds afresh for every
 * request (see ActorProgress).
 */
class ImageReference
{
    // D: without it, `$` also matches before a trailing newline, and such a reference would
    // slip past AssetUsage's exact match.
    protected const TEMPLATE = '/^template:([a-z0-9-]+)\/([a-z0-9_-]+\.svg)$/D';
    protected const ASSET = '/^asset:([1-9]\d{0,9})$/D';

    /**
     * @var WeakMap<User, array<int, string>>
     */
    protected WeakMap $assetPaths;

    public function __construct(
        protected Factory $filesystem
    ) {
        $this->assetPaths = new WeakMap();
    }

    /**
     * Whether the reference is well formed and, for an Asset, the Asset exists.
     */
    public function isValid(?string $reference): bool
    {
        if ($reference === null || preg_match(self::TEMPLATE, $reference) === 1) {
            return true;
        }

        if (preg_match(self::ASSET, $reference, $matches) === 1) {
            return Asset::query()->whereKey((int) $matches[1])->exists();
        }

        return false;
    }

    public function url(?string $reference, Context $context): ?string
    {
        if ($reference === null || preg_match(self::ASSET, $reference, $matches) !== 1) {
            return $this->templateUrl($reference);
        }

        $actor = $context->getActor();
        $this->assetPaths[$actor] ??= $this->loadAssetPaths();
        $path = $this->assetPaths[$actor][(int) $matches[1]] ?? null;

        return $path === null ? null : $this->disk()->url($path);
    }

    public function templateUrl(?string $reference): ?string
    {
        if ($reference === null || ! preg_match(self::TEMPLATE, $reference, $matches)) {
            return null;
        }

        return $this->disk()->url("extensions/fof-scavenger-hunt/templates/$matches[1]/$matches[2]");
    }

    /**
     * @return array<int, string>
     */
    protected function loadAssetPaths(): array
    {
        $paths = [];

        foreach (Asset::query()->get(['id', 'path']) as $asset) {
            $paths[$asset->id] = $asset->path;
        }

        return $paths;
    }

    protected function disk(): Cloud
    {
        /** @var Cloud $disk */
        $disk = $this->filesystem->disk('flarum-assets');

        return $disk;
    }
}
