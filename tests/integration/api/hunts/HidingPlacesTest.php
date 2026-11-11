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

use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\HuntMap\HuntMapRepository;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use FoF\ScavengerHunt\Tests\integration\SeedsForumContent;
use Illuminate\Contracts\Cache\Repository;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

class HidingPlacesTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;
    use SeedsForumContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-audit', 'fof-scavenger-hunt');

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->moderatorUser()],
            'group_user' => [$this->moderatorMembership()],
            Discussion::class => [$this->discussionRow(1)],
            Post::class => [$this->postRow(11, 1, 1)],
            'fof_scavenger_hunts' => [
                $this->huntRow(),
                $this->huntRow(['id' => 2, 'slug' => 'draft', 'is_enabled' => false]),
            ],
            'fof_scavenger_parts' => [
                $this->partRow(['id' => 1, 'mode' => 'fixed']),
                $this->partRow(['id' => 2, 'mode' => 'assigned']),
                $this->partRow(['id' => 3, 'hunt_id' => 2]),
            ],
            'fof_scavenger_locations' => [
                $this->locationRow(['id' => 1, 'part_id' => 1, 'spot' => 'page.corner']),
                $this->locationRow(['id' => 2, 'part_id' => 2, 'spot' => 'page.footer']),
            ],
        ]);
    }

    protected function apply(int $userId, int $huntId, array $body): ResponseInterface
    {
        return $this->send($this->request('POST', "/api/scavenger-hunts/$huntId/hiding-places", [
            'authenticatedAs' => $userId,
            'json' => $body,
        ]));
    }

    protected function locations(int $partId): array
    {
        return $this->database()->table('fof_scavenger_locations')->where('part_id', $partId)->orderBy('id')
            ->get(['spot', 'route', 'context_type', 'context_id'])
            ->map(fn ($row) => [$row->spot, $row->route, $row->context_type, $row->context_id === null ? null : (int) $row->context_id])
            ->all();
    }

    protected function generation(): int
    {
        return (int) $this->app()->getContainer()->make(Repository::class)->get(HuntMapRepository::GENERATION_KEY, 0);
    }

    protected function proposal(): array
    {
        return ['parts' => [[
            'id' => 1,
            'locations' => [
                ['spot' => 'post.footer', 'route' => null, 'contextType' => 'post', 'contextId' => 11],
                ['spot' => 'page.sidebar', 'route' => 'index', 'contextType' => null, 'contextId' => null],
            ],
        ]]];
    }

    #[Test]
    public function the_listed_parts_get_the_new_places_and_the_others_keep_theirs(): void
    {
        $generation = $this->generation();

        $response = $this->apply(3, 1, $this->proposal());

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame([['post.footer', null, 'post', 11], ['page.sidebar', 'index', null, null]], $this->locations(1));
        $this->assertSame([['page.footer', null, null, null]], $this->locations(2));

        $this->assertSame($generation + 1, $this->generation(), 'the hunt map is invalidated once');

        $body = $this->body($response);
        $this->assertSame('scavenger-hunts', $body['data']['type']);
        $this->assertSame('1', $body['data']['id']);

        $locations = array_values(array_filter($body['included'], fn (array $r) => $r['type'] === 'scavenger-hunt-locations'));
        $this->assertEqualsCanonicalizing(['post.footer', 'page.sidebar', 'page.footer'], array_column(array_column($locations, 'attributes'), 'spot'));
        $this->assertCount(2, array_filter($body['included'], fn (array $r) => $r['type'] === 'scavenger-hunt-parts'));
    }

    /**
     * Audit rows written since the given id: the audit log isn't reset between tests.
     *
     * @return list<string>
     */
    protected function loggedSince(int $id): array
    {
        return $this->database()->table('audit_log')->where('id', '>', $id)->orderBy('id')->pluck('action')->all();
    }

    protected function lastLogId(): int
    {
        return (int) $this->database()->table('audit_log')->max('id');
    }

    #[Test]
    public function every_change_is_logged(): void
    {
        $this->app();
        $since = $this->lastLogId();

        $this->apply(3, 1, $this->proposal());

        $actions = $this->loggedSince($since);

        $this->assertSame([
            'fof-scavenger-hunt.location_deleted',
            'fof-scavenger-hunt.location_created',
            'fof-scavenger-hunt.location_created',
        ], $actions);
    }

    #[Test]
    public function one_invalid_place_saves_nothing(): void
    {
        $generation = $this->generation();
        $since = $this->lastLogId();

        $response = $this->apply(3, 1, ['parts' => [
            $this->proposal()['parts'][0],
            ['id' => 2, 'locations' => [['spot' => 'post.footer', 'route' => null, 'contextType' => 'post', 'contextId' => 999]]],
        ]]);

        $this->assertEquals(422, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame('/data/attributes/parts.1.locations.0.contextId', $this->body($response)['errors'][0]['source']['pointer']);

        $this->assertSame([['page.corner', null, null, null]], $this->locations(1));
        $this->assertSame([['page.footer', null, null, null]], $this->locations(2));
        $this->assertSame($generation, $this->generation());
        $this->assertSame([], $this->loggedSince($since));
    }

    #[Test]
    public function an_empty_list_clears_a_parts_places(): void
    {
        $response = $this->apply(3, 1, ['parts' => [['id' => 2, 'locations' => []]]]);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame([], $this->locations(2));
        $this->assertSame([['page.corner', null, null, null]], $this->locations(1));
    }

    #[Test]
    public function only_hunt_managers_may_save(): void
    {
        $this->assertEquals(403, $this->apply(2, 1, $this->proposal())->getStatusCode());
        $this->assertSame([['page.corner', null, null, null]], $this->locations(1));
    }

    #[Test]
    public function a_hunt_the_actor_cannot_see_is_not_found(): void
    {
        $this->assertEquals(404, $this->apply(2, 2, ['parts' => [['id' => 3, 'locations' => []]]])->getStatusCode());
        $this->assertEquals(404, $this->apply(3, 99, $this->proposal())->getStatusCode());
    }

    #[Test]
    public function parts_must_belong_to_the_hunt_and_be_listed_once(): void
    {
        $this->assertEquals(422, $this->apply(3, 1, ['parts' => [['id' => 3, 'locations' => []]]])->getStatusCode());
        $this->assertEquals(422, $this->apply(3, 1, ['parts' => [['id' => 1, 'locations' => []], ['id' => 1, 'locations' => []]]])->getStatusCode());
        $this->assertEquals(422, $this->apply(3, 1, ['parts' => [['id' => 1]]])->getStatusCode());
        $this->assertSame([['page.corner', null, null, null]], $this->locations(1));
    }
}
