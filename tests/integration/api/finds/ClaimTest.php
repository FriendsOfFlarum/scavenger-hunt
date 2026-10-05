<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\api\finds;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Event\HuntCompleted;
use FoF\ScavengerHunt\Event\PartFound;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;

class ClaimTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    /**
     * @var array<string, int>
     */
    protected array $dispatched = ['found' => 0, 'completed' => 0];

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->moderatorUser(), $this->secondMember()],
            'group_user' => [$this->moderatorMembership()],
            'fof_scavenger_hunts' => [$this->huntRow()],
            'fof_scavenger_parts' => [
                $this->partRow(['id' => 1, 'name' => 'Carrot nose']),
                $this->partRow(['id' => 2, 'name' => 'Top hat', 'sort' => 1]),
            ],
            'fof_scavenger_locations' => [
                $this->locationRow(['id' => 1, 'part_id' => 1, 'spot' => 'page.corner']),
                $this->locationRow(['id' => 2, 'part_id' => 2, 'spot' => 'page.footer']),
            ],
        ]);

        $events = $this->app()->getContainer()->make(Dispatcher::class);
        $events->listen(PartFound::class, function () {
            $this->dispatched['found']++;
        });
        $events->listen(HuntCompleted::class, function () {
            $this->dispatched['completed']++;
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function tokenFor(int $userId, string $spot): string
    {
        $data = $this->body($this->lookup($userId, 'index', [['spot' => $spot]]))['data'];

        $this->assertNotEmpty($data, "no encounter for user $userId at $spot");

        return $data[0]['attributes']['token'];
    }

    protected function includedHunt(array $body): array
    {
        return collect($body['included'])->firstWhere('type', 'scavenger-hunts')['attributes'];
    }

    #[Test]
    public function claiming_a_token_records_the_find(): void
    {
        $response = $this->claim(2, $this->tokenFor(2, 'page.corner'));

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $body = $this->body($response);
        $this->assertSame('scavenger-hunt-finds', $body['data']['type']);
        $this->assertSame('1', $body['data']['relationships']['part']['data']['id']);
        $this->assertSame(1, $this->includedHunt($body)['foundCount']);
        $this->assertNull($this->includedHunt($body)['completedAt']);

        $this->assertTrue($this->database()->table('fof_scavenger_finds')->where(['part_id' => 1, 'user_id' => 2])->exists());
        $encounter = $this->database()->table('fof_scavenger_encounters')->first();
        $this->assertNotNull($encounter->claimed_at);
        $this->assertNotNull($encounter->claim_ms);
        $this->assertSame(1, $this->dispatched['found']);
        $this->assertSame(0, $this->dispatched['completed']);
    }

    #[Test]
    public function the_last_part_completes_the_hunt(): void
    {
        $this->claim(2, $this->tokenFor(2, 'page.corner'));
        $response = $this->claim(2, $this->tokenFor(2, 'page.footer'));

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertNotNull($this->includedHunt($this->body($response))['completedAt']);
        $this->assertSame(1, $this->database()->table('fof_scavenger_completions')->where(['hunt_id' => 1, 'user_id' => 2])->count());
        $this->assertSame(2, $this->dispatched['found']);
        $this->assertSame(1, $this->dispatched['completed']);
    }

    #[Test]
    public function a_forged_or_empty_token_is_refused(): void
    {
        $this->assertEquals(422, $this->claim(2, str_repeat('a', 64))->getStatusCode());
        $this->assertEquals(422, $this->claim(2, '')->getStatusCode());
    }

    #[Test]
    public function a_malformed_token_is_refused_like_any_other(): void
    {
        $response = $this->send($this->request('POST', '/api/scavenger-hunt-finds', [
            'authenticatedAs' => 2,
            'json' => ['data' => ['type' => 'scavenger-hunt-finds', 'attributes' => ['token' => ['not', 'a', 'string']]]],
        ]));

        $this->assertEquals(422, $response->getStatusCode());
    }

    #[Test]
    public function someone_elses_token_is_refused(): void
    {
        $this->assertEquals(422, $this->claim(4, $this->tokenFor(2, 'page.corner'))->getStatusCode());
    }

    #[Test]
    public function a_token_works_once(): void
    {
        $token = $this->tokenFor(2, 'page.corner');

        $this->assertEquals(201, $this->claim(2, $token)->getStatusCode());
        $this->assertEquals(422, $this->claim(2, $token)->getStatusCode());
    }

    #[Test]
    public function an_expired_token_is_refused(): void
    {
        $token = $this->tokenFor(2, 'page.corner');

        Carbon::setTestNow(Carbon::now()->addMinutes(11));

        $this->assertEquals(422, $this->claim(2, $token)->getStatusCode());
    }

    #[Test]
    public function a_token_dies_with_its_hunt(): void
    {
        $token = $this->tokenFor(2, 'page.corner');

        $this->send($this->request('PATCH', '/api/scavenger-hunts/1', [
            'authenticatedAs' => 3,
            'json' => ['data' => ['type' => 'scavenger-hunts', 'id' => '1', 'attributes' => ['isEnabled' => false]]],
        ]));

        $this->assertEquals(422, $this->claim(2, $token)->getStatusCode());
    }

    #[Test]
    public function every_failure_looks_the_same(): void
    {
        $forged = $this->body($this->claim(2, str_repeat('a', 64)));
        $token = $this->tokenFor(2, 'page.corner');
        $this->claim(2, $token);
        $reused = $this->body($this->claim(2, $token));

        $this->assertSame($forged, $reused);
    }

    #[Test]
    public function guests_cannot_claim(): void
    {
        $this->assertEquals(401, $this->claim(null, str_repeat('a', 64))->getStatusCode());
    }

    #[Test]
    public function hunt_managers_cannot_claim_by_default(): void
    {
        $this->assertEquals(403, $this->claim(3, str_repeat('a', 64))->getStatusCode());
    }

    #[Test]
    public function claiming_a_part_already_found_is_harmless(): void
    {
        // setUp() has already booted the app to listen for events, so seed directly.
        $this->database()->table('fof_scavenger_locations')->insert($this->locationRow(['id' => 3, 'part_id' => 1, 'spot' => 'page.sidebar']));

        $corner = $this->tokenFor(2, 'page.corner');
        $sidebar = $this->tokenFor(2, 'page.sidebar');

        $first = $this->claim(2, $corner);
        $second = $this->claim(2, $sidebar);

        $this->assertEquals(201, $first->getStatusCode());
        $this->assertEquals(201, $second->getStatusCode(), (string) $second->getBody());
        $this->assertSame($this->body($first)['data']['id'], $this->body($second)['data']['id']);
        $this->assertSame(1, $this->database()->table('fof_scavenger_finds')->count());
        $this->assertSame(1, $this->dispatched['found']);
    }
}
