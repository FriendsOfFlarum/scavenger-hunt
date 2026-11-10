<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration;

use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Laminas\Diactoros\UploadedFile;
use Psr\Http\Message\ResponseInterface;

/**
 * Asset fixtures and the upload request. Files on the assets disk outlive the test's
 * database transaction, so tests using this call `removeAssetFiles()` in tearDown.
 */
trait InteractsWithAssets
{
    /**
     * @var list<string>
     */
    protected array $uploadTempFiles = [];

    protected function assetsDisk(): Filesystem
    {
        return $this->app()->getContainer()->make('filesystem')->disk('flarum-assets');
    }

    protected function removeAssetFiles(): void
    {
        foreach ($this->uploadTempFiles as $path) {
            @unlink($path);
        }

        $this->uploadTempFiles = [];

        $this->assetsDisk()->deleteDirectory('scavenger-hunt');
    }

    protected function assetRow(array $overrides = []): array
    {
        $id = $overrides['id'] ?? 1;

        return array_merge([
            'id' => $id,
            'path' => "scavenger-hunt/asset-$id.png",
            'name' => "Asset $id",
            'mime_type' => 'image/png',
            'width' => 20,
            'height' => 10,
            'size' => 100,
            'user_id' => 1,
            'created_at' => Carbon::now()->subMinutes(100 - $id)->toDateTimeString(),
        ], $overrides);
    }

    protected function upload(?int $userId, string $contents, string $filename = 'image.png', string $clientType = 'application/octet-stream'): ResponseInterface
    {
        $path = tempnam(sys_get_temp_dir(), 'fof-scavenger-asset-');
        file_put_contents($path, $contents);
        $this->uploadTempFiles[] = $path;

        $request = $this->request('POST', '/api/scavenger-hunt/assets', $userId === null ? [] : ['authenticatedAs' => $userId])
            ->withUploadedFiles(['file' => new UploadedFile($path, strlen($contents), UPLOAD_ERR_OK, $filename, $clientType)]);

        return $this->send($userId === null ? $request->withAttribute('bypassCsrfToken', true) : $request);
    }

    protected function rasterImage(string $type, int $width = 20, int $height = 10): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 40, 40));

        ob_start();

        match ($type) {
            'png' => imagepng($image),
            'jpeg' => imagejpeg($image),
            'gif' => imagegif($image),
            'webp' => imagewebp($image),
            'bmp' => imagebmp($image),
        };

        return (string) ob_get_clean();
    }
}
