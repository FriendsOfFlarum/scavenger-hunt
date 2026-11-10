<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\api\templates;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\Test;

class TemplatesTest extends TestCase
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
    public function hunt_managers_can_list_templates(): void
    {
        $this->loadTranslations();

        $response = $this->send($this->request('GET', '/api/scavenger-hunt-templates', ['authenticatedAs' => 3]));

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $winter = collect($this->body($response)['data'])->firstWhere('id', 'winter');
        $this->assertNotNull($winter);
        $this->assertSame('Build a Snowman', $winter['attributes']['title']);
        $this->assertSame('Winter', $winter['attributes']['occasion']);
        $this->assertStringEndsWith('extensions/fof-scavenger-hunt/templates/winter/base.svg', $winter['attributes']['imageUrl']);
        $this->assertCount(7, $winter['attributes']['parts']);
        $this->assertContains('Carrot nose', array_column($winter['attributes']['parts'], 'name'));
    }

    #[Test]
    public function templates_are_listed_by_season_then_holiday_then_all_year(): void
    {
        $response = $this->send($this->request('GET', '/api/scavenger-hunt-templates', ['authenticatedAs' => 3]));

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame([
            'spring', 'summer', 'autumn', 'winter',
            'new-year', 'easter', 'ramadan', 'halloween', 'thanksgiving', 'christmas',
            'treasure', 'anniversary',
        ], array_column($this->body($response)['data'], 'id'));
    }

    #[Test]
    public function templates_return_image_references_alongside_urls(): void
    {
        $response = $this->send($this->request('GET', '/api/scavenger-hunt-templates', ['authenticatedAs' => 3]));

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $winter = collect($this->body($response)['data'])->firstWhere('id', 'winter')['attributes'];
        $this->assertSame('template:winter/base.svg', $winter['imageReference']);
        $this->assertArrayHasKey('completedImageReference', $winter);
        $this->assertNull($winter['completedImageReference']);

        $hat = collect($winter['parts'])->firstWhere('key', 'hat');
        $this->assertSame('template:winter/hat.svg', $hat['reference']);
        $this->assertStringEndsWith('extensions/fof-scavenger-hunt/templates/winter/hat.svg', $hat['imageUrl']);
    }

    #[Test]
    public function members_cannot_list_templates(): void
    {
        $this->assertEquals(403, $this->send($this->request('GET', '/api/scavenger-hunt-templates', ['authenticatedAs' => 2]))->getStatusCode());
    }

    #[Test]
    public function guests_cannot_list_templates(): void
    {
        $this->assertEquals(401, $this->send($this->request('GET', '/api/scavenger-hunt-templates'))->getStatusCode());
    }

    #[Test]
    public function creating_a_hunt_from_a_template_copies_its_parts(): void
    {
        $this->loadTranslations();

        $response = $this->send($this->request('POST', '/api/scavenger-hunts', [
            'authenticatedAs' => 3,
            'json' => ['data' => ['type' => 'scavenger-hunts', 'attributes' => ['title' => 'Snowman 2026', 'template' => 'winter']]],
        ]));

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        $huntId = (int) $this->body($response)['data']['id'];
        $hunt = $this->database()->table('fof_scavenger_hunts')->find($huntId);
        $this->assertSame('winter', $hunt->template);
        $this->assertSame('template:winter/base.svg', $hunt->image);

        $parts = $this->database()->table('fof_scavenger_parts')->where('hunt_id', $huntId)->orderBy('sort')->get();
        $this->assertCount(7, $parts);
        $this->assertSame('Snowball body', $parts[0]->name);
        $this->assertSame('template:winter/body.svg', $parts[0]->image);
        $this->assertSame('fixed', $parts[0]->mode);
        $this->assertEquals(['x' => 28.75, 'y' => 53.75, 'width' => 42.5, 'z' => 2], json_decode($parts[0]->layer, true));
    }

    #[Test]
    public function an_unknown_template_is_rejected(): void
    {
        $response = $this->send($this->request('POST', '/api/scavenger-hunts', [
            'authenticatedAs' => 3,
            'json' => ['data' => ['type' => 'scavenger-hunts', 'attributes' => ['title' => 'Mystery', 'template' => 'nope']]],
        ]));

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertSame(0, $this->database()->table('fof_scavenger_hunts')->count());
    }
}
