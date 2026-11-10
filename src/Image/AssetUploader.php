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

use Carbon\Carbon;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\User;
use FoF\ScavengerHunt\Asset;
use FoF\ScavengerHunt\Event\AssetUploaded;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Intervention\Gif\Exceptions\DecoderException as GifDecoderException;
use Intervention\Image\Decoders\BinaryImageDecoder;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\ImageManager;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

/**
 * Stores an admin's upload as an Asset. The type is read from the file's content; the name and
 * media type the browser sends are never trusted.
 */
class AssetUploader
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    // Bounds what decoding a raster image can allocate, as core's image validator does.
    protected const MAX_PIXELS = 24_000_000;

    protected const RASTER_TYPES = [
        IMAGETYPE_PNG => ['png', 'image/png'],
        IMAGETYPE_JPEG => ['jpg', 'image/jpeg'],
        IMAGETYPE_GIF => ['gif', 'image/gif'],
        IMAGETYPE_WEBP => ['webp', 'image/webp'],
    ];

    public function __construct(
        protected Factory $filesystem,
        protected ImageManager $images,
        protected SvgSanitizer $svg,
        protected TranslatorInterface $translator,
        protected Dispatcher $events,
    ) {
    }

    public function upload(UploadedFileInterface $file, User $actor): Asset
    {
        if ($file->getError() === UPLOAD_ERR_INI_SIZE || $file->getError() === UPLOAD_ERR_FORM_SIZE) {
            throw $this->invalid('asset_too_large');
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw $this->invalid('asset_type_invalid');
        }

        // A null size can't be checked against the cap, so it doesn't pass it.
        $size = $file->getSize();

        if ($size === null || $size > self::MAX_BYTES) {
            throw $this->invalid('asset_too_large');
        }

        $contents = (string) $file->getStream();

        if (strlen($contents) > self::MAX_BYTES) {
            throw $this->invalid('asset_too_large');
        }

        [$contents, $extension, $mimeType, $width, $height] = $this->read($contents);

        $path = 'scavenger-hunt/'.bin2hex(random_bytes(16)).".$extension";
        $disk = $this->disk();
        if (! $disk->put($path, $contents)) {
            throw new RuntimeException("Could not store the uploaded image at $path.");
        }

        $asset = new Asset();
        $asset->path = $path;
        $asset->name = $this->name($file->getClientFilename());
        $asset->mime_type = $mimeType;
        $asset->width = $width;
        $asset->height = $height;
        $asset->size = strlen($contents);
        $asset->user_id = $actor->id;
        $asset->created_at = Carbon::now();

        try {
            $asset->save();
        } catch (Throwable $e) {
            $disk->delete($path);

            throw $e;
        }

        $this->events->dispatch(new AssetUploaded($asset, $actor));

        return $asset;
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: int|null, 4: int|null} contents, extension, MIME type, width, height
     */
    protected function read(string $contents): array
    {
        $info = $contents === '' ? false : @getimagesizefromstring($contents);

        if ($info !== false && isset(self::RASTER_TYPES[$info[2]])) {
            [$extension, $mimeType] = self::RASTER_TYPES[$info[2]];
            [$width, $height] = $info;

            if ($width < 1 || $height < 1 || $width > 65535 || $height > 65535 || $width * $height > self::MAX_PIXELS) {
                throw $this->invalid('asset_type_invalid');
            }

            try {
                $this->images->read($contents, BinaryImageDecoder::class);
            } catch (DecoderException|GifDecoderException) {
                throw $this->invalid('asset_type_invalid');
            }

            return [$contents, $extension, $mimeType, $width, $height];
        }

        $clean = $contents === '' ? null : $this->svg->sanitize($contents);

        if ($clean === null) {
            throw $this->invalid('asset_type_invalid');
        }

        [$width, $height] = $this->svg->dimensions($clean);

        return [$clean, 'svg', 'image/svg+xml', $width, $height];
    }

    protected function name(?string $filename): string
    {
        $name = trim(mb_scrub(pathinfo((string) $filename, PATHINFO_FILENAME), 'UTF-8'));

        return $name === '' ? 'image' : mb_substr($name, 0, 100);
    }

    protected function disk(): Filesystem
    {
        return $this->filesystem->disk('flarum-assets');
    }

    protected function invalid(string $key): ValidationException
    {
        return new ValidationException(['file' => $this->translator->trans("fof-scavenger-hunt.api.$key")]);
    }
}
