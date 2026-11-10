<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\audit;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Setting;
use FoF\ScavengerHunt\Tests\integration\InteractsWithAssets;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\Test;

class AuditTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;
    use InteractsWithAssets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-audit', 'fof-scavenger-hunt');

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->moderatorUser()],
            'group_user' => [$this->moderatorMembership()],
            'fof_scavenger_hunts' => [$this->huntRow()],
            'fof_scavenger_parts' => [$this->partRow()],
            'fof_scavenger_locations' => [$this->locationRow()],
        ]);
    }

    protected function tearDown(): void
    {
        $this->removeAssetFiles();

        parent::tearDown();
    }

    protected function logged(string $action): array
    {
        return $this->database()->table('audit_log')->where('action', "fof-scavenger-hunt.$action")->get()
            ->map(fn ($row) => ['actor' => (int) $row->actor_id, 'payload' => json_decode($row->payload, true)])->all();
    }

    #[Test]
    public function hunt_manager_actions_are_logged(): void
    {
        $this->send($this->request('POST', '/api/scavenger-hunts', [
            'authenticatedAs' => 3,
            'json' => ['data' => ['type' => 'scavenger-hunts', 'attributes' => ['title' => 'Spring']]],
        ]));

        $created = $this->logged('hunt_created');
        $this->assertCount(1, $created);
        $this->assertSame(3, $created[0]['actor']);
        $this->assertSame('Spring', $created[0]['payload']['title']);

        $this->send($this->request('POST', '/api/scavenger-hunts/1/reset-progress', [
            'authenticatedAs' => 3,
            'json' => ['data' => ['attributes' => ['userId' => 2]]],
        ]));

        $this->assertSame([['actor' => 3, 'payload' => ['hunt_id' => 1, 'user_id' => 2]]], $this->logged('progress_reset'));
    }

    #[Test]
    public function completions_are_logged_but_individual_finds_are_not(): void
    {
        $token = $this->body($this->lookup(2, 'index', [['spot' => 'page.corner']]))['data'][0]['attributes']['token'];
        $this->claim(2, $token);

        $this->assertSame([['actor' => 2, 'payload' => ['hunt_id' => 1, 'user_id' => 2]]], $this->logged('hunt_completed'));
        $this->assertSame(0, $this->database()->table('audit_log')->where('action', 'like', '%part_found%')->count());
    }

    #[Test]
    public function crossing_the_daily_lookup_limit_is_logged_once(): void
    {
        $this->setting(Setting::DAILY_LOOKUP_LIMIT, 1);

        $this->lookup(2, 'index', []);
        $this->lookup(2, 'index', []);
        $this->lookup(2, 'index', []);

        $this->assertSame([['actor' => 2, 'payload' => ['user_id' => 2]]], $this->logged('lookup_limit_reached'));
    }

    #[Test]
    public function asset_uploads_and_deletions_are_logged(): void
    {
        $response = $this->upload(1, $this->rasterImage('png'), 'Golden carrot.png');
        $id = (int) $this->body($response)['data']['id'];

        $this->assertEquals([['actor' => 1, 'payload' => ['asset_id' => $id, 'name' => 'Golden carrot']]], $this->logged('asset_uploaded'));

        $this->send($this->request('DELETE', "/api/scavenger-hunt-assets/$id", ['authenticatedAs' => 1]));

        $this->assertEquals([['actor' => 1, 'payload' => ['asset_id' => $id, 'name' => 'Golden carrot']]], $this->logged('asset_deleted'));
    }
}
