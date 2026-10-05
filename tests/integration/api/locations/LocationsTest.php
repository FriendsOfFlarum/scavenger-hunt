<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\api\locations;

use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Extend\HidingSpots;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

class LocationsTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $this->extend(
            (new HidingSpots())->add('test.post', 'test', ['discussion'], 'post')
        );

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->moderatorUser()],
            'group_user' => [$this->moderatorMembership()],
            Discussion::class => [['id' => 1, 'title' => 'Snow talk', 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1]],
            Post::class => [['id' => 1, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Let it snow</p></t>', 'number' => 1]],
            'fof_scavenger_hunts' => [$this->huntRow()],
            'fof_scavenger_parts' => [$this->partRow()],
            'fof_scavenger_locations' => [$this->locationRow(['id' => 1, 'route' => 'index'])],
        ]);
    }

    protected function createLocation(int $userId, array $attributes): ResponseInterface
    {
        return $this->send($this->request('POST', '/api/scavenger-hunt-locations', [
            'authenticatedAs' => $userId,
            'json' => ['data' => [
                'type' => 'scavenger-hunt-locations',
                'attributes' => $attributes,
                'relationships' => ['part' => ['data' => ['type' => 'scavenger-hunt-parts', 'id' => '1']]],
            ]],
        ]));
    }

    #[Test]
    public function a_hunt_manager_can_hide_a_part_on_any_page_or_one_page(): void
    {
        $anyPage = $this->createLocation(3, ['spot' => 'page.corner']);
        $this->assertEquals(201, $anyPage->getStatusCode(), (string) $anyPage->getBody());
        $this->assertTrue($this->body($anyPage)['data']['attributes']['isAvailable']);

        $onePage = $this->createLocation(3, ['spot' => 'page.header', 'route' => 'index']);
        $this->assertEquals(201, $onePage->getStatusCode(), (string) $onePage->getBody());
    }

    #[Test]
    public function the_spot_must_be_registered(): void
    {
        $this->assertEquals(422, $this->createLocation(3, ['spot' => 'nope'])->getStatusCode());
    }

    #[Test]
    public function the_route_must_be_a_page_the_spot_appears_on(): void
    {
        $this->assertEquals(422, $this->createLocation(3, ['spot' => 'page.corner', 'route' => 'logoutPage'])->getStatusCode());
        $this->assertEquals(422, $this->createLocation(3, ['spot' => 'page.corner', 'route' => 'no-such-route'])->getStatusCode());
        $this->assertEquals(422, $this->createLocation(3, ['spot' => 'test.post', 'route' => 'index'])->getStatusCode());
    }

    #[Test]
    public function a_spot_without_a_context_takes_no_item(): void
    {
        $this->assertEquals(422, $this->createLocation(3, ['spot' => 'page.corner', 'contextType' => 'post', 'contextId' => 1])->getStatusCode());
    }

    #[Test]
    public function an_item_must_exist_and_match_the_spot(): void
    {
        $this->assertEquals(422, $this->createLocation(3, ['spot' => 'test.post', 'contextType' => 'post', 'contextId' => 999])->getStatusCode());
        $this->assertEquals(422, $this->createLocation(3, ['spot' => 'test.post', 'contextType' => 'user', 'contextId' => 2])->getStatusCode());

        $response = $this->createLocation(3, ['spot' => 'test.post', 'route' => 'discussion', 'contextId' => 1]);
        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame('post', $this->body($response)['data']['attributes']['contextType']);
    }

    #[Test]
    public function any_item_of_a_kind_is_allowed(): void
    {
        $response = $this->createLocation(3, ['spot' => 'test.post']);

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame('post', $this->body($response)['data']['attributes']['contextType']);
        $this->assertNull($this->body($response)['data']['attributes']['contextId']);
    }

    #[Test]
    public function members_cannot_create_locations(): void
    {
        $this->assertEquals(403, $this->createLocation(2, ['spot' => 'page.corner'])->getStatusCode());
    }

    #[Test]
    public function players_never_receive_locations(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/scavenger-hunt-parts/1', ['authenticatedAs' => 2])
                ->withQueryParams(['include' => 'locations'])
        );

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $body = $this->body($response);
        $this->assertArrayNotHasKey('locations', $body['data']['relationships'] ?? []);
        $this->assertEmpty($body['included'] ?? []);
        $this->assertStringNotContainsString('page.corner', (string) $response->getBody());
    }

    #[Test]
    public function hunt_managers_can_include_locations(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/scavenger-hunt-parts/1', ['authenticatedAs' => 3])
                ->withQueryParams(['include' => 'locations'])
        );

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame('scavenger-hunt-locations', $this->body($response)['included'][0]['type']);
        $this->assertSame('index', $this->body($response)['included'][0]['attributes']['route']);
    }
}
