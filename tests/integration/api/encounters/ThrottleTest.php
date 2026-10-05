<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\api\encounters;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Event\LookupLimitReached;
use FoF\ScavengerHunt\Setting;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;

class ThrottleTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
        ]);
    }

    #[Test]
    public function lookups_beyond_the_per_minute_limit_are_refused(): void
    {
        $this->setting(Setting::LOOKUP_RATE_LIMIT, 2);

        $this->assertEquals(200, $this->lookup(2, 'index', [])->getStatusCode());
        $this->assertEquals(200, $this->lookup(2, 'index', [])->getStatusCode());
        $this->assertEquals(429, $this->lookup(2, 'index', [])->getStatusCode());
    }

    #[Test]
    public function guests_are_limited_too(): void
    {
        $this->setting(Setting::LOOKUP_RATE_LIMIT, 1);

        $this->assertEquals(200, $this->lookup(null, 'index', [])->getStatusCode());
        $this->assertEquals(429, $this->lookup(null, 'index', [])->getStatusCode());
    }

    #[Test]
    public function lookups_beyond_the_daily_limit_are_refused_and_reported_once(): void
    {
        $this->setting(Setting::LOOKUP_RATE_LIMIT, 100);
        $this->setting(Setting::DAILY_LOOKUP_LIMIT, 2);

        $reported = 0;
        $this->app()->getContainer()->make(Dispatcher::class)->listen(LookupLimitReached::class, function () use (&$reported) {
            $reported++;
        });

        $this->assertEquals(200, $this->lookup(2, 'index', [])->getStatusCode());
        $this->assertEquals(200, $this->lookup(2, 'index', [])->getStatusCode());
        $this->assertEquals(429, $this->lookup(2, 'index', [])->getStatusCode());
        $this->assertEquals(429, $this->lookup(2, 'index', [])->getStatusCode());
        $this->assertSame(1, $reported);
    }

    #[Test]
    public function other_api_requests_are_not_counted(): void
    {
        $this->setting(Setting::LOOKUP_RATE_LIMIT, 1);

        $this->send($this->request('GET', '/api', ['authenticatedAs' => 2]));
        $this->send($this->request('GET', '/api', ['authenticatedAs' => 2]));

        $this->assertEquals(200, $this->lookup(2, 'index', [])->getStatusCode());
    }
}
