<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\api\hunts;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Event\HuntCreated;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

class ManageHuntsTest extends TestCase
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
            'fof_scavenger_hunts' => [$this->huntRow()],
            'fof_scavenger_parts' => [$this->partRow()],
        ]);
    }

    protected function create(?int $userId, array $attributes): ResponseInterface
    {
        $options = ['json' => ['data' => ['type' => 'scavenger-hunts', 'attributes' => $attributes]]];

        if ($userId !== null) {
            $options['authenticatedAs'] = $userId;
        }

        $request = $this->request('POST', '/api/scavenger-hunts', $options);

        return $this->send($userId === null ? $request->withAttribute('bypassCsrfToken', true) : $request);
    }

    #[Test]
    public function a_hunt_manager_can_create_a_hunt(): void
    {
        $events = [];
        $this->app()->getContainer()->make(Dispatcher::class)->listen(HuntCreated::class, function (HuntCreated $event) use (&$events) {
            $events[] = $event;
        });

        $response = $this->create(3, ['title' => 'Spring Fling!']);

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $attributes = $this->body($response)['data']['attributes'];
        $this->assertSame('spring-fling', $attributes['slug']);
        $this->assertFalse($attributes['isEnabled']);
        $this->assertTrue($attributes['guestsCanSee']);

        $seed = $this->database()->table('fof_scavenger_hunts')->where('slug', 'spring-fling')->value('seed');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $seed);
        $this->assertCount(1, $events);
        $this->assertSame(3, $events[0]->actor->id);
    }

    #[Test]
    public function generated_slugs_are_unique(): void
    {
        $response = $this->create(3, ['title' => 'Snowman']);

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame('snowman-2', $this->body($response)['data']['attributes']['slug']);
    }

    #[Test]
    public function an_explicit_duplicate_slug_is_rejected(): void
    {
        $this->assertEquals(422, $this->create(3, ['title' => 'Another', 'slug' => 'snowman'])->getStatusCode());
    }

    #[Test]
    public function members_cannot_create_hunts(): void
    {
        $this->assertEquals(403, $this->create(2, ['title' => 'Nope'])->getStatusCode());
    }

    #[Test]
    public function guests_cannot_create_hunts(): void
    {
        $this->assertEquals(401, $this->create(null, ['title' => 'Nope'])->getStatusCode());
    }

    #[Test]
    public function a_hunt_must_end_after_it_starts(): void
    {
        $response = $this->create(3, ['title' => 'Backwards', 'startsAt' => '2026-12-10T00:00:00+00:00', 'endsAt' => '2026-12-01T00:00:00+00:00']);

        $this->assertEquals(422, $response->getStatusCode());
    }

    #[Test]
    public function an_invalid_image_reference_is_rejected(): void
    {
        $this->assertEquals(422, $this->create(3, ['title' => 'Bad image', 'image' => 'https://evil.example/x.svg'])->getStatusCode());
    }

    #[Test]
    public function a_hunt_manager_can_update_a_hunt(): void
    {
        $response = $this->send($this->request('PATCH', '/api/scavenger-hunts/1', [
            'authenticatedAs' => 3,
            'json' => ['data' => ['type' => 'scavenger-hunts', 'id' => '1', 'attributes' => ['title' => 'Snowman 2026', 'isEnabled' => false]]],
        ]));

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame('Snowman 2026', $this->database()->table('fof_scavenger_hunts')->where('id', 1)->value('title'));
        $this->assertFalse((bool) $this->database()->table('fof_scavenger_hunts')->where('id', 1)->value('is_enabled'));
    }

    #[Test]
    public function members_cannot_update_hunts(): void
    {
        $response = $this->send($this->request('PATCH', '/api/scavenger-hunts/1', [
            'authenticatedAs' => 2,
            'json' => ['data' => ['type' => 'scavenger-hunts', 'id' => '1', 'attributes' => ['title' => 'Hijack']]],
        ]));

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function a_hunt_manager_can_delete_a_hunt_and_its_parts(): void
    {
        $response = $this->send($this->request('DELETE', '/api/scavenger-hunts/1', ['authenticatedAs' => 3]));

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertSame(0, $this->database()->table('fof_scavenger_hunts')->count());
        $this->assertSame(0, $this->database()->table('fof_scavenger_parts')->count());
    }
}
