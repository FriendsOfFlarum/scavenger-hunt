<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\api\assets;

use Carbon\Carbon;
use Flarum\Group\PermissionCache;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Tests\integration\InteractsWithAssets;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class UploadAssetTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;
    use InteractsWithAssets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->moderatorUser()],
            'group_user' => [$this->moderatorMembership()],
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeAssetFiles();

        parent::tearDown();
    }

    public static function rasterTypes(): array
    {
        return [
            'png' => ['png', 'image/png'],
            'jpeg' => ['jpeg', 'image/jpeg'],
            'gif' => ['gif', 'image/gif'],
            'webp' => ['webp', 'image/webp'],
        ];
    }

    #[Test]
    #[DataProvider('rasterTypes')]
    public function an_admin_uploads_a_raster_image(string $type, string $mimeType): void
    {
        $contents = $this->rasterImage($type, 20, 10);

        $response = $this->upload(1, $contents, "Carrot nose.$type", $mimeType);

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        $data = $this->body($response)['data'];
        $this->assertSame('scavenger-hunt-assets', $data['type']);
        $this->assertSame('Carrot nose', $data['attributes']['name']);
        $this->assertSame($mimeType, $data['attributes']['mimeType']);
        $this->assertSame(20, $data['attributes']['width']);
        $this->assertSame(10, $data['attributes']['height']);
        $this->assertSame(strlen($contents), $data['attributes']['size']);
        $this->assertFalse($data['attributes']['isInUse']);

        $row = $this->database()->table('fof_scavenger_assets')->find($data['id']);
        $extension = $type === 'jpeg' ? 'jpg' : $type;
        $this->assertMatchesRegularExpression("~^scavenger-hunt/[a-f0-9]{32}\.$extension$~", $row->path);
        $this->assertSame(1, (int) $row->user_id);
        $this->assertStringEndsWith($row->path, $data['attributes']['url']);
        $this->assertSame($contents, $this->assetsDisk()->get($row->path));
    }

    #[Test]
    public function the_type_comes_from_the_content_not_the_name(): void
    {
        $response = $this->upload(1, $this->rasterImage('png'), 'notes.txt', 'text/plain');

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame('image/png', $this->body($response)['data']['attributes']['mimeType']);
        $this->assertStringEndsWith('.png', $this->database()->table('fof_scavenger_assets')->value('path'));
    }

    public static function rejectedFiles(): array
    {
        return [
            'text named as a png' => ['just some text', 'image.png', 'image/png'],
            'php named as a png' => ['<?php echo "hi"; ?>', 'image.png', 'image/png'],
            'html' => ['<html><body onload="alert(1)">hi</body></html>', 'page.svg', 'image/svg+xml'],
            'svg that does not parse' => ['<svg xmlns="http://www.w3.org/2000/svg"><rect', 'broken.svg', 'image/svg+xml'],
            'empty file' => ['', 'empty.png', 'image/png'],
            // Browsers don't render these as images, so they would only ever show as broken.
            'svg without the svg namespace' => ['<svg viewBox="0 0 10 10"><rect width="5" height="5"/></svg>', 'plain.svg', 'image/svg+xml'],
            'svg in the xhtml namespace' => ['<svg xmlns="http://www.w3.org/1999/xhtml" viewBox="0 0 10 10"><rect width="5" height="5"/></svg>', 'xhtml.svg', 'image/svg+xml'],
        ];
    }

    #[Test]
    #[DataProvider('rejectedFiles')]
    public function other_files_are_rejected(string $contents, string $name, string $clientType): void
    {
        $response = $this->upload(1, $contents, $name, $clientType);

        $this->assertEquals(422, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(0, $this->database()->table('fof_scavenger_assets')->count());
        $this->assertSame([], $this->assetsDisk()->allFiles('scavenger-hunt'));
    }

    #[Test]
    #[DataProvider('rasterTypes')]
    public function a_damaged_image_is_rejected(string $type, string $mimeType): void
    {
        // The header still reads, so only decoding the whole file finds the damage.
        $full = $this->rasterImage($type, 40, 40);
        $response = $this->upload(1, substr($full, 0, intdiv(strlen($full), 2)), "damaged.$type", $mimeType);

        $this->assertEquals(422, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(0, $this->database()->table('fof_scavenger_assets')->count());
    }

    #[Test]
    public function a_bmp_is_rejected(): void
    {
        $response = $this->upload(1, $this->rasterImage('bmp'), 'image.bmp', 'image/bmp');

        $this->assertEquals(422, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(0, $this->database()->table('fof_scavenger_assets')->count());
    }

    #[Test]
    public function files_over_2_mb_are_rejected(): void
    {
        $contents = $this->rasterImage('png');
        $contents .= str_repeat("\0", 2 * 1024 * 1024 + 1 - strlen($contents));

        $response = $this->upload(1, $contents, 'huge.png', 'image/png');

        $this->assertEquals(422, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(0, $this->database()->table('fof_scavenger_assets')->count());
    }

    #[Test]
    public function a_file_of_exactly_2_mb_is_accepted(): void
    {
        $contents = $this->rasterImage('png');
        $contents .= str_repeat("\0", 2 * 1024 * 1024 - strlen($contents));

        $response = $this->upload(1, $contents, 'big.png', 'image/png');

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function a_missing_file_is_rejected(): void
    {
        $response = $this->send($this->request('POST', '/api/scavenger-hunt/assets', ['authenticatedAs' => 1]));

        $this->assertEquals(422, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function an_svg_is_stored_sanitized(): void
    {
        $svg = <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="400" height="300" onload="alert(1)">
              <script>alert(2)</script>
              <rect width="10" height="10" fill="red" onclick="alert(3)"/>
              <image href="https://evil.example/tracker.png" width="10" height="10"/>
              <use xlink:href="https://evil.example/sprite.svg#icon"/>
              <a xlink:href="javascript:alert(4)"><circle r="5"/></a>
            </svg>
            SVG;

        $response = $this->upload(1, $svg, 'snowman.svg', 'image/svg+xml');

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        $attributes = $this->body($response)['data']['attributes'];
        $this->assertSame('image/svg+xml', $attributes['mimeType']);
        $this->assertSame(400, $attributes['width']);
        $this->assertSame(300, $attributes['height']);

        $path = $this->database()->table('fof_scavenger_assets')->value('path');
        $this->assertMatchesRegularExpression('~^scavenger-hunt/[a-f0-9]{32}\.svg$~', $path);

        $stored = $this->assetsDisk()->get($path);
        $this->assertSame(strlen($stored), $attributes['size']);
        $this->assertStringContainsString('<rect', $stored);
        $this->assertStringContainsString('<circle', $stored);

        foreach (['<script', 'alert(', 'onload', 'onclick', 'evil.example', 'javascript:'] as $dangerous) {
            $this->assertStringNotContainsString($dangerous, $stored);
        }
    }

    public static function svgDimensions(): array
    {
        return [
            'width and height' => ['width="120" height="80" viewBox="0 0 400 400"', 120, 80],
            'pixel units' => ['width="120px" height="80.4px"', 120, 80],
            'viewBox only' => ['viewBox="0 0 400 300"', 400, 300],
            'percentages fall back to the viewBox' => ['width="100%" height="100%" viewBox="0, 0, 64, 32"', 64, 32],
            'neither' => ['', null, null],
        ];
    }

    #[Test]
    #[DataProvider('svgDimensions')]
    public function svg_dimensions_come_from_its_attributes(string $attributes, ?int $width, ?int $height): void
    {
        $response = $this->upload(1, "<svg xmlns=\"http://www.w3.org/2000/svg\" $attributes><rect width=\"1\" height=\"1\"/></svg>", 'shape.svg');

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        $data = $this->body($response)['data']['attributes'];
        $this->assertSame($width, $data['width']);
        $this->assertSame($height, $data['height']);
    }

    #[Test]
    public function a_name_that_is_not_utf_8_is_cleaned(): void
    {
        $response = $this->upload(1, $this->rasterImage('png'), "snow\xC3\x28man.png");

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertTrue(mb_check_encoding($this->body($response)['data']['attributes']['name'], 'UTF-8'));
    }

    #[Test]
    public function the_name_drops_the_extension_and_is_cut_to_100_characters(): void
    {
        $response = $this->upload(1, $this->rasterImage('png'), str_repeat('a', 150).'.png');

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(str_repeat('a', 100), $this->body($response)['data']['attributes']['name']);
    }

    public static function withoutUploadPermission(): array
    {
        return [
            'guest' => [null, 401],
            'member' => [2, 403],
        ];
    }

    #[Test]
    #[DataProvider('withoutUploadPermission')]
    public function only_those_allowed_to_upload_images_can_upload(?int $userId, int $status): void
    {
        $response = $this->upload($userId, $this->rasterImage('png'));

        $this->assertEquals($status, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(0, $this->database()->table('fof_scavenger_assets')->count());
        $this->assertSame([], $this->assetsDisk()->allFiles('scavenger-hunt'));
    }

    #[Test]
    public function moderators_can_upload_images_by_default(): void
    {
        $response = $this->upload(3, $this->rasterImage('png'), 'Snowball.png');

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(3, (int) $this->database()->table('fof_scavenger_assets')->value('user_id'));
    }

    #[Test]
    public function a_hunt_manager_without_the_upload_permission_cannot_upload(): void
    {
        $this->app();
        $this->database()->table('group_permission')->where('permission', 'fof-scavenger-hunt.uploadImages')->delete();
        // Query-builder writes fire no model events, so the moderators' cached permission set is still stale.
        $this->app()->getContainer()->make(PermissionCache::class)->flush();

        $response = $this->upload(3, $this->rasterImage('png'));

        $this->assertEquals(403, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(0, $this->database()->table('fof_scavenger_assets')->count());
    }

    #[Test]
    public function uploaders_who_are_not_admins_get_30_uploads_an_hour(): void
    {
        $rows = [];

        for ($id = 1; $id <= 30; $id++) {
            $rows[] = $this->assetRow(['id' => $id, 'user_id' => 3, 'created_at' => Carbon::now()->subMinutes(59)->toDateTimeString()]);
        }

        // An upload from more than an hour ago no longer counts.
        $rows[] = $this->assetRow(['id' => 31, 'user_id' => 3, 'created_at' => Carbon::now()->subMinutes(61)->toDateTimeString()]);

        $this->prepareDatabase(['fof_scavenger_assets' => $rows]);

        $response = $this->upload(3, $this->rasterImage('png'));

        $this->assertEquals(429, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame(31, $this->database()->table('fof_scavenger_assets')->count());
        $this->assertSame([], $this->assetsDisk()->allFiles('scavenger-hunt'));

        $this->database()->table('fof_scavenger_assets')->where('id', 1)->update(['created_at' => Carbon::now()->subMinutes(61)->toDateTimeString()]);

        $this->assertEquals(201, $this->upload(3, $this->rasterImage('png'))->getStatusCode());
    }

    #[Test]
    public function admins_have_no_upload_limit(): void
    {
        $rows = [];

        for ($id = 1; $id <= 30; $id++) {
            $rows[] = $this->assetRow(['id' => $id, 'user_id' => 1, 'created_at' => Carbon::now()->subMinutes(5)->toDateTimeString()]);
        }

        $this->prepareDatabase(['fof_scavenger_assets' => $rows]);

        $this->assertEquals(201, $this->upload(1, $this->rasterImage('png'))->getStatusCode());
    }
}
