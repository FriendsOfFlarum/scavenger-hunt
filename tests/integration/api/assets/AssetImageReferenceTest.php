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
use FoF\ScavengerHunt\Tests\integration\InteractsWithAssets;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

class AssetImageReferenceTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;
    use InteractsWithAssets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $assets = [];
        $hunts = [];
        $parts = [];

        for ($id = 1; $id <= 12; $id++) {
            $assets[] = $this->assetRow(['id' => $id]);
        }

        for ($id = 1; $id <= 3; $id++) {
            $hunts[] = $this->huntRow(['id' => $id, 'slug' => "hunt-$id", 'image' => 'asset:'.$id, 'completed_image' => 'asset:'.($id + 3)]);
            $parts[] = $this->partRow(['id' => $id, 'hunt_id' => $id, 'image' => 'asset:'.($id + 6)]);
        }

        $parts[] = $this->partRow(['id' => 4, 'hunt_id' => 1, 'image' => 'asset:99']);
        $parts[] = $this->partRow(['id' => 5, 'hunt_id' => 1, 'image' => 'template:winter/nose.svg']);

        $this->prepareDatabase([
            'users' => [$this->moderatorUser()],
            'group_user' => [$this->moderatorMembership()],
            'fof_scavenger_assets' => $assets,
            'fof_scavenger_hunts' => $hunts,
            'fof_scavenger_parts' => $parts,
        ]);
    }

    protected function save(string $method, string $path, string $type, array $attributes, array $relationships = []): ResponseInterface
    {
        return $this->send($this->request($method, $path, [
            'authenticatedAs' => 3,
            'json' => ['data' => array_filter([
                'type' => $type,
                'id' => $method === 'PATCH' ? basename($path) : null,
                'attributes' => $attributes,
                'relationships' => $relationships,
            ])],
        ]));
    }

    #[Test]
    public function hunts_and_parts_show_their_asset_urls(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/scavenger-hunts')->withQueryParams(['include' => 'parts'])
        );

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $body = $this->body($response);

        $hunts = array_column($body['data'], 'attributes', 'id');
        $this->assertStringEndsWith('scavenger-hunt/asset-1.png', $hunts['1']['imageUrl']);
        $this->assertStringEndsWith('scavenger-hunt/asset-4.png', $hunts['1']['completedImageUrl']);
        $this->assertStringEndsWith('scavenger-hunt/asset-3.png', $hunts['3']['imageUrl']);

        $parts = array_column($body['included'], 'attributes', 'id');
        $this->assertStringEndsWith('scavenger-hunt/asset-7.png', $parts['1']['imageUrl']);
        $this->assertStringEndsWith('scavenger-hunt/asset-9.png', $parts['3']['imageUrl']);
        $this->assertNull($parts['4']['imageUrl']);
        $this->assertStringEndsWith('templates/winter/nose.svg', $parts['5']['imageUrl']);
    }

    #[Test]
    public function every_asset_on_a_response_loads_in_one_query(): void
    {
        $this->send($this->request('GET', '/api/scavenger-hunts'));

        $this->database()->enableQueryLog();
        $this->database()->flushQueryLog();

        $this->send($this->request('GET', '/api/scavenger-hunts')->withQueryParams(['include' => 'parts']));

        $assetQueries = array_filter(
            $this->database()->getQueryLog(),
            fn (array $query) => str_contains($query['query'], 'fof_scavenger_assets')
        );
        $this->assertCount(1, $assetQueries);
    }

    #[Test]
    public function a_hunt_can_use_an_asset(): void
    {
        $response = $this->save('PATCH', '/api/scavenger-hunts/1', 'scavenger-hunts', ['image' => 'asset:12']);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertStringEndsWith('scavenger-hunt/asset-12.png', $this->body($response)['data']['attributes']['imageUrl']);
    }

    #[Test]
    public function an_asset_that_does_not_exist_is_rejected(): void
    {
        $this->assertEquals(422, $this->save('PATCH', '/api/scavenger-hunts/1', 'scavenger-hunts', ['image' => 'asset:99'])->getStatusCode());
        $this->assertEquals(422, $this->save('PATCH', '/api/scavenger-hunts/1', 'scavenger-hunts', ['completedImage' => 'asset:99'])->getStatusCode());
        $this->assertEquals(422, $this->save('PATCH', '/api/scavenger-hunt-parts/1', 'scavenger-hunt-parts', ['image' => 'asset:99'])->getStatusCode());
        $this->assertEquals(422, $this->save('POST', '/api/scavenger-hunt-parts', 'scavenger-hunt-parts', ['name' => 'Hat', 'image' => 'asset:99'], [
            'hunt' => ['data' => ['type' => 'scavenger-hunts', 'id' => '1']],
        ])->getStatusCode());

        $this->assertSame('asset:1', $this->database()->table('fof_scavenger_hunts')->where('id', 1)->value('image'));
        $this->assertSame('asset:7', $this->database()->table('fof_scavenger_parts')->where('id', 1)->value('image'));
    }

    #[Test]
    public function a_part_can_use_an_asset(): void
    {
        $response = $this->save('POST', '/api/scavenger-hunt-parts', 'scavenger-hunt-parts', ['name' => 'Hat', 'image' => 'asset:12'], [
            'hunt' => ['data' => ['type' => 'scavenger-hunts', 'id' => '1']],
        ]);

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertStringEndsWith('scavenger-hunt/asset-12.png', $this->body($response)['data']['attributes']['imageUrl']);
    }
}
