<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\api\spots;

use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Extend\HidingSpots;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\Test;

class HidingSpotsTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->moderatorUser()],
            'group_user' => [$this->moderatorMembership()],
        ]);
    }

    #[Test]
    public function hunt_managers_get_the_spots_and_page_routes(): void
    {
        $this->loadTranslations();

        $response = $this->send($this->request('GET', '/api/scavenger-hunt-spots', ['authenticatedAs' => 3]));

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $body = $this->body($response);

        $spots = collect($body['data'])->keyBy('id');
        $this->assertTrue($spots->has('page.header'));
        $this->assertTrue($spots->has('page.footer'));
        $this->assertTrue($spots->has('page.sidebar'));
        $this->assertSame('Page corner (floating)', $spots['page.corner']['attributes']['label']);
        $this->assertNull($spots['page.corner']['attributes']['contextType']);

        $routes = array_column($body['meta']['routes'], 'name');
        $this->assertContains('index', $routes);
        $this->assertContains('discussion', $routes);
        $this->assertContains('user', $routes);
        $this->assertNotContains('logoutPage', $routes);
        $this->assertNotContains('confirmEmail', $routes);
        $this->assertNotContains('logout', $routes);
    }

    #[Test]
    public function other_extensions_can_register_spots(): void
    {
        $this->extend(
            (new HidingSpots())
                ->context('post', Post::class)
                ->add('acme.post-ribbon', 'acme.ribbon', ['discussion'], 'post')
        );

        $response = $this->send($this->request('GET', '/api/scavenger-hunt-spots', ['authenticatedAs' => 3]));

        $spot = collect($this->body($response)['data'])->firstWhere('id', 'acme.post-ribbon');
        $this->assertNotNull($spot);
        $this->assertSame('post', $spot['attributes']['contextType']);
        $this->assertSame(['discussion'], $spot['attributes']['routes']);
    }

    #[Test]
    public function a_broken_registration_from_another_extension_does_not_take_the_forum_down(): void
    {
        $this->extend(
            (new HidingSpots())
                ->add('page.corner', 'acme.duplicate', ['index'])
                ->add('acme.mystery', 'acme.mystery', null, 'no-such-context')
        );

        $this->assertEquals(200, $this->send($this->request('GET', '/api'))->getStatusCode());

        $response = $this->send($this->request('GET', '/api/scavenger-hunt-spots', ['authenticatedAs' => 3]));
        $spots = collect($this->body($response)['data'])->keyBy('id');

        $this->assertNull($spots['page.corner']['attributes']['routes'], 'the first registration wins');
        $this->assertFalse($spots->has('acme.mystery'));
    }

    #[Test]
    public function players_cannot_list_spots(): void
    {
        $this->assertEquals(403, $this->send($this->request('GET', '/api/scavenger-hunt-spots', ['authenticatedAs' => 2]))->getStatusCode());
        $this->assertEquals(401, $this->send($this->request('GET', '/api/scavenger-hunt-spots'))->getStatusCode());
    }
}
