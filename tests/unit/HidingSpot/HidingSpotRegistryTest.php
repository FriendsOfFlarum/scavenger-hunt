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
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HidingSpotRegistryTest extends TestCase
{
    protected function registry(array $definitions, array $contexts = [], array $excluded = []): HidingSpotRegistry
    {
        $routes = (new RouteCollection())
            ->get('/', 'index', 'handler')
            ->get('/d/{id}', 'discussion', 'handler')
            ->get('/logout', 'logoutPage', 'handler')
            ->post('/logout', 'logout', 'handler');

        return new HidingSpotRegistry($definitions, $contexts, $excluded, fn () => $routes);
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
    public function duplicate_keys_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry([$this->spot('page.corner'), $this->spot('page.corner')]);
    }

    #[Test]
    public function spots_must_use_a_registered_context(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry([$this->spot('post.footer', ['discussion'], 'post')]);
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
