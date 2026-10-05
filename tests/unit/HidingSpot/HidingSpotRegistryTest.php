<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\unit\HidingSpot;

use Flarum\Http\RouteCollection;
use Flarum\Post\Post;
use FoF\ScavengerHunt\HidingSpot\HidingSpot;
use FoF\ScavengerHunt\HidingSpot\HidingSpotRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HidingSpotRegistryTest extends TestCase
{
    /**
     * @var list<string>
     */
    protected array $reported = [];

    protected function registry(array $definitions, array $contexts = [], array $excluded = []): HidingSpotRegistry
    {
        $routes = (new RouteCollection())
            ->get('/', 'index', 'handler')
            ->get('/d/{id}', 'discussion', 'handler')
            ->get('/logout', 'logoutPage', 'handler')
            ->post('/logout', 'logout', 'handler');

        return new HidingSpotRegistry($definitions, $contexts, $excluded, fn () => $routes, function (string $message) {
            $this->reported[] = $message;
        });
    }

    protected function spot(string $key, ?array $routes = null, ?string $context = null): array
    {
        return ['key' => $key, 'label' => "label.$key", 'routes' => $routes, 'context' => $context, 'constrain' => null];
    }

    #[Test]
    public function routes_match_exactly_or_by_trailing_wildcard(): void
    {
        $any = new HidingSpot('a', 'a', null, null, null);
        $this->assertTrue($any->allowsRoute('anything'));

        $user = new HidingSpot('b', 'b', ['discussion', 'user*'], null, null);
        $this->assertTrue($user->allowsRoute('discussion'));
        $this->assertTrue($user->allowsRoute('user'));
        $this->assertTrue($user->allowsRoute('user.posts'));
        $this->assertFalse($user->allowsRoute('index'));
        $this->assertFalse($user->allowsRoute('discussions'));
    }

    #[Test]
    public function a_duplicate_key_keeps_the_first_registration_and_is_reported(): void
    {
        $registry = $this->registry([$this->spot('page.corner', null), $this->spot('page.corner', ['index'])]);

        $this->assertNull($registry->get('page.corner')?->routes);
        $this->assertCount(1, $this->reported);
        $this->assertStringContainsString('page.corner', $this->reported[0]);
    }

    #[Test]
    public function a_spot_with_an_unregistered_context_is_skipped_and_reported(): void
    {
        $registry = $this->registry([$this->spot('post.footer', ['discussion'], 'post'), $this->spot('page.corner')]);

        $this->assertNull($registry->get('post.footer'));
        $this->assertNotNull($registry->get('page.corner'));
        $this->assertCount(1, $this->reported);
    }

    #[Test]
    public function contexts_resolve_to_their_model(): void
    {
        $registry = $this->registry([$this->spot('post.footer', ['discussion'], 'post')], ['post' => Post::class]);

        $this->assertSame(Post::class, $registry->contextModel('post'));
        $this->assertNull($registry->contextModel('nope'));
        $this->assertSame('post', $registry->get('post.footer')?->context);
        $this->assertNull($registry->get('nope'));
    }

    #[Test]
    public function page_routes_are_get_routes_minus_exclusions_sorted_by_name(): void
    {
        $registry = $this->registry([], [], ['logoutPage']);

        $this->assertSame(
            [['name' => 'discussion', 'path' => '/d/{id}'], ['name' => 'index', 'path' => '/']],
            $registry->pageRoutes()
        );
        $this->assertTrue($registry->isPageRoute('index'));
        $this->assertFalse($registry->isPageRoute('logoutPage'));
        $this->assertFalse($registry->isPageRoute('logout'));
    }
}
