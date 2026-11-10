<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\gdpr;

use Flarum\Gdpr\DataProcessor;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Data\ScavengerHunt;
use PHPUnit\Framework\Attributes\Test;

class WithoutGdprTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');
    }

    #[Test]
    public function the_extension_boots_and_registers_no_data_type_without_gdpr(): void
    {
        $response = $this->send($this->request('GET', '/api'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayNotHasKey(ScavengerHunt::class, (new DataProcessor())->types());
    }
}
