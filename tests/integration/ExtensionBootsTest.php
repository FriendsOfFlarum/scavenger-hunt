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

use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ExtensionBootsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');
    }

    #[Test]
    public function forum_api_responds_with_the_extension_enabled(): void
    {
        $response = $this->send($this->request('GET', '/api'));

        $this->assertEquals(200, $response->getStatusCode());
    }
}
