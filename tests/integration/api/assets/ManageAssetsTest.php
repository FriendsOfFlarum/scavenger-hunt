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

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Event\AssetDeleted;
use FoF\ScavengerHunt\Tests\integration\InteractsWithAssets;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

class ManageAssetsTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;
    use InteractsWithAssets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $assets = [];

        for ($id = 1; $id <= 8; $id++) {
            $assets[] = $this->assetRow(['id' => $id]);
        }

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->moderatorUser()],
            'group_user' => [$this->moderatorMembership()],
            'fof_scavenger_assets' => $assets,
            'fof_scavenger_hunts' => [
                $this->huntRow(['image' => 'asset:1']),
                $this->huntRow(['id' => 2, 'slug' => 'second', 'completed_image' => 'asset:2']),
            ],
            'fof_scavenger_parts' => [
                $this->partRow(['image' => 'asset:3']),
                $this->partRow(['id' => 2, 'image' => 'template:winter/nose.svg']),
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeAssetFiles();

        parent::tearDown();
    }

    protected function index(?int $userId, array $query = []): ResponseInterface
    {
        return $this->send(
            $this->request('GET', '/api/scavenger-hunt-assets', $userId === null ? [] : ['authenticatedAs' => $userId])
                ->withQueryParams($query)
        );
    }

    protected function rename(?int $userId, int $id, string $name): ResponseInterface
    {
        $request = $this->request('PATCH', "/api/scavenger-hunt-assets/$id", [
            ...($userId === null ? [] : ['authenticatedAs' => $userId]),
            'json' => ['data' => ['type' => 'scavenger-hunt-assets', 'id' => (string) $id, 'attributes' => ['name' => $name]]],
        ]);

        return $this->send($userId === null ? $request->withAttribute('bypassCsrfToken', true) : $request);
    }

    protected function delete(?int $userId, int $id): ResponseInterface
    {
        $request = $this->request('DELETE', "/api/scavenger-hunt-assets/$id", $userId === null ? [] : ['authenticatedAs' => $userId]);

        return $this->send($userId === null ? $request->withAttribute('bypassCsrfToken', true) : $request);
    }

    public static function managers(): array
    {
        return ['admin' => [1], 'hunt manager' => [3]];
    }

    #[Test]
    #[DataProvider('managers')]
    public function hunt_managers_list_assets_newest_first(int $userId): void
    {
        $response = $this->index($userId);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $data = $this->body($response)['data'];
        $this->assertSame(['8', '7', '6', '5', '4', '3', '2', '1'], array_column($data, 'id'));

        $first = $data[7]['attributes'];
        $this->assertSame('Asset 1', $first['name']);
        $this->assertStringEndsWith('scavenger-hunt/asset-1.png', $first['url']);
        $this->assertSame('image/png', $first['mimeType']);
        $this->assertSame(20, $first['width']);
        $this->assertSame(10, $first['height']);
        $this->assertSame(100, $first['size']);
        $this->assertArrayHasKey('createdAt', $first);
    }

    #[Test]
    public function the_list_is_paginated(): void
    {
        $body = $this->body($this->index(1, ['page' => ['limit' => 3]]));

        $this->assertSame(['8', '7', '6'], array_column($body['data'], 'id'));
        $this->assertArrayHasKey('next', $body['links']);
    }

    public static function notManagers(): array
    {
        return ['guest' => [null, 401], 'member' => [2, 403]];
    }

    #[Test]
    #[DataProvider('notManagers')]
    public function others_cannot_list_assets(?int $userId, int $status): void
    {
        $this->assertEquals($status, $this->index($userId)->getStatusCode());
    }

    #[Test]
    public function assets_referenced_by_a_hunt_or_part_are_in_use(): void
    {
        $data = $this->body($this->index(3))['data'];

        $inUse = array_column(array_column($data, 'attributes', 'id'), 'isInUse', 'name');

        $this->assertSame(
            ['Asset 8' => false, 'Asset 7' => false, 'Asset 6' => false, 'Asset 5' => false, 'Asset 4' => false, 'Asset 3' => true, 'Asset 2' => true, 'Asset 1' => true],
            $inUse
        );
    }

    #[Test]
    public function checking_use_costs_the_same_for_any_page_size(): void
    {
        $count = function (int $limit): int {
            $queries = 0;
            $this->database()->listen(function () use (&$queries) {
                $queries++;
            });

            $this->assertCount($limit, $this->body($this->index(1, ['page' => ['limit' => $limit]]))['data']);

            return $queries;
        };

        $count(2);

        $this->assertSame($count(2), $count(7));
    }

    #[Test]
    public function an_admin_renames_an_asset(): void
    {
        $response = $this->rename(1, 4, 'Golden carrot');

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame('Golden carrot', $this->body($response)['data']['attributes']['name']);
        $this->assertSame('Golden carrot', $this->database()->table('fof_scavenger_assets')->where('id', 4)->value('name'));
    }

    #[Test]
    public function a_name_cannot_be_empty_or_too_long(): void
    {
        $this->assertEquals(422, $this->rename(1, 4, '')->getStatusCode());
        $this->assertEquals(422, $this->rename(1, 4, str_repeat('a', 101))->getStatusCode());
        $this->assertSame('Asset 4', $this->database()->table('fof_scavenger_assets')->where('id', 4)->value('name'));
    }

    public static function notAdmins(): array
    {
        return ['guest' => [null, 401], 'member' => [2, 403], 'hunt manager' => [3, 403]];
    }

    #[Test]
    #[DataProvider('notAdmins')]
    public function only_admins_rename_and_delete_assets(?int $userId, int $status): void
    {
        $this->assetsDisk()->put('scavenger-hunt/asset-4.png', 'image');

        $this->assertEquals($status, $this->rename($userId, 4, 'Mine now')->getStatusCode());
        $this->assertEquals($status, $this->delete($userId, 4)->getStatusCode());

        $this->assertSame('Asset 4', $this->database()->table('fof_scavenger_assets')->where('id', 4)->value('name'));
        $this->assertTrue($this->assetsDisk()->exists('scavenger-hunt/asset-4.png'));
    }

    #[Test]
    public function deleting_an_asset_removes_its_file(): void
    {
        $this->assetsDisk()->put('scavenger-hunt/asset-4.png', 'image');
        $deleted = [];
        $this->app()->getContainer()->make(Dispatcher::class)->listen(AssetDeleted::class, function (AssetDeleted $event) use (&$deleted) {
            $deleted[] = [$event->asset->id, $event->actor->id];
        });

        $response = $this->delete(1, 4);

        $this->assertEquals(204, $response->getStatusCode(), (string) $response->getBody());
        $this->assertNull($this->database()->table('fof_scavenger_assets')->find(4));
        $this->assertFalse($this->assetsDisk()->exists('scavenger-hunt/asset-4.png'));
        $this->assertSame([[4, 1]], $deleted);
    }

    public static function usedAssets(): array
    {
        return ['hunt image' => [1], 'hunt completed image' => [2], 'part image' => [3]];
    }

    #[Test]
    #[DataProvider('usedAssets')]
    public function an_asset_in_use_cannot_be_deleted(int $id): void
    {
        $this->loadTranslations();
        $this->assetsDisk()->put("scavenger-hunt/asset-$id.png", 'image');

        $response = $this->delete(1, $id);

        $this->assertEquals(422, $response->getStatusCode(), (string) $response->getBody());
        $this->assertStringContainsString("can't be deleted", $this->body($response)['errors'][0]['detail']);
        $this->assertNotNull($this->database()->table('fof_scavenger_assets')->find($id));
        $this->assertTrue($this->assetsDisk()->exists("scavenger-hunt/asset-$id.png"));
    }
}
