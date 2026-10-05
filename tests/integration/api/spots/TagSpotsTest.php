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

use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\Test;

class TagSpotsTest extends TestCase
{
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'fof-scavenger-hunt');

        $this->prepareDatabase([
            'users' => [$this->moderatorUser()],
            'group_user' => [$this->moderatorMembership()],
        ]);
    }

    #[Test]
    public function tag_tiles_are_a_hiding_spot_when_tags_is_enabled(): void
    {
        $body = $this->body($this->send($this->request('GET', '/api/scavenger-hunt-spots', ['authenticatedAs' => 3])));

        $tile = collect($body['data'])->firstWhere('id', 'tags.tile');
        $this->assertNotNull($tile);
        $this->assertSame('tag', $tile['attributes']['contextType']);
        $this->assertSame(['tags'], $tile['attributes']['routes']);
    }
}
