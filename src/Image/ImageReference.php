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

use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Contracts\Filesystem\Factory;

/**
 * Hunts and Parts store images as references rather than URLs, so a change of
 * assets host or CDN applies without touching the database:
 *
 *   template:<template-key>/<file>.svg  — shipped in assets/templates, published on enable
 *   asset:<id>                          — an uploaded Asset (milestone 4)
 */
class ImageReference
{
    protected const TEMPLATE = '/^template:([a-z0-9-]+)\/([a-z0-9_-]+\.svg)$/';
    protected const ASSET = '/^asset:[1-9]\d*$/';

    public function __construct(
        protected Factory $filesystem
    ) {
    }

    public static function isValid(?string $reference): bool
    {
        return $reference === null
            || preg_match(self::TEMPLATE, $reference) === 1
            || preg_match(self::ASSET, $reference) === 1;
    }

    public function url(?string $reference): ?string
    {
        if ($reference === null || ! preg_match(self::TEMPLATE, $reference, $matches)) {
            return null;
        }

        /** @var Cloud $disk */
        $disk = $this->filesystem->disk('flarum-assets');

        return $disk->url("extensions/fof-scavenger-hunt/templates/$matches[1]/$matches[2]");
    }
}
