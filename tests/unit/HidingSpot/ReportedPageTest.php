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
use FoF\ScavengerHunt\HidingSpot\HidingSpotRegistry;
use FoF\ScavengerHunt\HidingSpot\ReportedPage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReportedPageTest extends TestCase
{
    protected function registry(int $extraSpots = 0): HidingSpotRegistry
    {
        $definitions = [
            ['key' => 'page.corner', 'label' => 'x', 'routes' => null, 'context' => null, 'constrain' => null],
            ['key' => 'post.footer', 'label' => 'x', 'routes' => ['discussion'], 'context' => 'post', 'constrain' => null],
        ];

        for ($i = 0; $i < $extraSpots; $i++) {
            $definitions[] = ['key' => "test.$i", 'label' => 'x', 'routes' => null, 'context' => null, 'constrain' => null];
        }

        return new HidingSpotRegistry($definitions, ['post' => Post::class], [], fn () => new RouteCollection());
    }

    #[Test]
    public function untrusted_entries_are_dropped_not_rejected(): void
    {
        $page = ReportedPage::fromRequest([
            'route' => 'discussion',
            'routeParams' => ['id' => '12-snow-talk', 'nested' => ['x'], 7 => 'numeric key'],
            'spots' => [
                ['spot' => 'page.corner'],
                ['spot' => 'page.corner'],
                ['spot' => 'nope'],
                'not an array',
                ['spot' => 'post.footer', 'contextType' => 'post', 'contextId' => 5],
                ['spot' => 'post.footer', 'contextType' => 'user', 'contextId' => 6],
                ['spot' => 'post.footer', 'contextType' => 'post', 'contextId' => 'abc'],
                ['spot' => 'post.footer', 'contextType' => 'post'],
                ['spot' => 'page.corner', 'contextType' => 'post', 'contextId' => 1],
            ],
        ], $this->registry());

        $this->assertSame('discussion', $page->route);
        $this->assertSame(['id' => '12-snow-talk'], $page->params);
        $this->assertSame(12, $page->intParam('id'));
        $this->assertNull($page->intParam('missing'));
        $this->assertCount(2, $page->spots);
        $this->assertSame('page.corner', $page->spots[0]->spot);
        $this->assertNull($page->spots[0]->contextType);
        $this->assertSame(['post.footer', 'post', 5], [$page->spots[1]->spot, $page->spots[1]->contextType, $page->spots[1]->contextId]);
    }

    #[Test]
    public function spots_not_allowed_on_the_route_are_dropped(): void
    {
        $page = ReportedPage::fromRequest([
            'route' => 'index',
            'spots' => [['spot' => 'post.footer', 'contextType' => 'post', 'contextId' => 5]],
        ], $this->registry());

        $this->assertSame([], $page->spots);
    }

    #[Test]
    public function reports_are_capped(): void
    {
        $spots = [];

        for ($i = 0; $i < 30; $i++) {
            $spots[] = ['spot' => 'post.footer', 'contextType' => 'post', 'contextId' => $i + 1];
        }

        $page = ReportedPage::fromRequest(['route' => 'discussion', 'spots' => $spots], $this->registry());
        $this->assertCount(ReportedPage::MAX_ITEMS_PER_KIND, $page->spots);

        $many = array_map(fn (int $i) => ['spot' => "test.$i"], range(0, 79));
        $page = ReportedPage::fromRequest(['route' => 'index', 'spots' => $many], $this->registry(80));
        $this->assertCount(ReportedPage::MAX_SPOTS, $page->spots);
    }
}
