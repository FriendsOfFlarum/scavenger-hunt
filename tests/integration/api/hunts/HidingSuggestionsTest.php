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
use FoF\ScavengerHunt\Lookup\RandomSource;
use FoF\ScavengerHunt\Tests\integration\FixedRandomSource;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use FoF\ScavengerHunt\Tests\integration\SeedsForumContent;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

class HidingSuggestionsTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;
    use SeedsForumContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->moderatorUser()],
            'group_user' => [$this->moderatorMembership()],
            Discussion::class => [$this->discussionRow(1), $this->discussionRow(2)],
            Post::class => [$this->postRow(11, 1, 1), $this->postRow(12, 1, 2), $this->postRow(21, 2, 1)],
            'fof_scavenger_hunts' => [
                $this->huntRow(),
                $this->huntRow(['id' => 2, 'slug' => 'draft', 'is_enabled' => false]),
            ],
            'fof_scavenger_parts' => [
                $this->partRow(['id' => 1, 'mode' => 'fixed']),
                $this->partRow(['id' => 2, 'mode' => 'assigned']),
                $this->partRow(['id' => 3, 'hunt_id' => 2]),
            ],
        ]);
    }

    protected function suggest(?int $userId, int $huntId, array $body): ResponseInterface
    {
        $options = ['json' => $body];

        if ($userId !== null) {
            $options['authenticatedAs'] = $userId;
        }

        $request = $this->request('POST', "/api/scavenger-hunts/$huntId/hiding-suggestions", $options);

        return $this->send($userId === null ? $request->withAttribute('bypassCsrfToken', true) : $request);
    }

    #[Test]
    public function a_hunt_manager_gets_places_for_each_part_without_saving_anything(): void
    {
        $response = $this->suggest(3, 1, ['parts' => [2, 1]]);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $data = $this->body($response)['data'];
        $this->assertSame([2, 1], array_column($data, 'partId'));
        $this->assertCount(3, $data[0]['places']);
        $this->assertCount(1, $data[1]['places']);
        $this->assertSame(['spot', 'route', 'contextType', 'contextId', 'label', 'url', 'fallback'], array_keys($data[1]['places'][0]));

        $this->assertSame(0, $this->database()->table('fof_scavenger_locations')->count());
    }

    #[Test]
    public function a_re_roll_never_repeats_an_excluded_place(): void
    {
        $this->app()->getContainer()->instance(RandomSource::class, new FixedRandomSource(0));

        $first = $this->body($this->suggest(3, 1, ['parts' => [1]]))['data'][0]['places'][0];
        $exclude = [['spot' => $first['spot'], 'contextType' => $first['contextType'], 'contextId' => $first['contextId']]];

        $response = $this->suggest(3, 1, ['parts' => [1], 'exclude' => $exclude]);
        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        $again = $this->body($response)['data'][0]['places'][0];
        $this->assertNotSame([$first['spot'], $first['contextId']], [$again['spot'], $again['contextId']]);
    }

    #[Test]
    public function only_hunt_managers_may_ask(): void
    {
        $this->assertEquals(401, $this->suggest(null, 1, ['parts' => [1]])->getStatusCode());
        $this->assertEquals(403, $this->suggest(2, 1, ['parts' => [1]])->getStatusCode());
    }

    #[Test]
    public function a_hunt_the_actor_cannot_see_is_not_found(): void
    {
        $this->assertEquals(404, $this->suggest(2, 2, ['parts' => [3]])->getStatusCode());
        $this->assertEquals(404, $this->suggest(3, 99, ['parts' => [1]])->getStatusCode());
    }

    #[Test]
    public function parts_must_belong_to_the_hunt(): void
    {
        $this->assertEquals(422, $this->suggest(3, 1, ['parts' => [1, 3]])->getStatusCode());
        $this->assertEquals(422, $this->suggest(3, 1, ['parts' => [999]])->getStatusCode());
        $this->assertEquals(422, $this->suggest(3, 1, [])->getStatusCode());
        $this->assertEquals(422, $this->suggest(3, 1, ['parts' => [1], 'exclude' => [['contextId' => 1]]])->getStatusCode());
    }
}
