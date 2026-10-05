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

use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Setting;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\Test;

class PreciseSpotsTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');
        $this->setting(Setting::MAX_PARTS_PER_PAGE, 5);

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->secondMember()],
            Discussion::class => [
                ['id' => 1, 'title' => 'Snow talk', 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1],
                ['id' => 2, 'title' => 'Other talk', 'user_id' => 2, 'first_post_id' => 2, 'comment_count' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>a</p></t>', 'number' => 1],
                ['id' => 2, 'discussion_id' => 2, 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>b</p></t>', 'number' => 1],
            ],
            'fof_scavenger_hunts' => [$this->huntRow()],
            'fof_scavenger_parts' => [
                $this->partRow(['id' => 1, 'name' => 'In any post footer']),
                $this->partRow(['id' => 2, 'name' => 'In the hero of discussion 1', 'sort' => 1]),
                $this->partRow(['id' => 3, 'name' => 'On member2\'s profile', 'sort' => 2]),
            ],
            'fof_scavenger_locations' => [
                $this->locationRow(['id' => 1, 'part_id' => 1, 'route' => 'discussion', 'spot' => 'post.footer', 'context_type' => 'post']),
                $this->locationRow(['id' => 2, 'part_id' => 2, 'spot' => 'discussion.hero', 'context_type' => 'discussion', 'context_id' => 1]),
                $this->locationRow(['id' => 3, 'part_id' => 3, 'spot' => 'user.sidebar', 'context_type' => 'user', 'context_id' => 4]),
            ],
        ]);
    }

    protected function partIds(string $route, array $spots, array $params): array
    {
        return array_map(fn (array $encounter) => $encounter['relationships']['part']['data']['id'], $this->body($this->lookup(2, $route, $spots, $params))['data']);
    }

    #[Test]
    public function a_post_only_counts_on_its_own_discussion_page(): void
    {
        $spot = fn (int $post) => ['spot' => 'post.footer', 'contextType' => 'post', 'contextId' => $post];

        $this->assertSame(['1'], $this->partIds('discussion', [$spot(1)], ['id' => '1-snow-talk']));
        $this->assertSame([], $this->partIds('discussion', [$spot(2)], ['id' => '1-snow-talk']));
    }

    #[Test]
    public function the_discussion_hero_only_counts_for_the_page_discussion(): void
    {
        $hero = ['spot' => 'discussion.hero', 'contextType' => 'discussion', 'contextId' => 1];

        $this->assertSame(['2'], $this->partIds('discussion', [$hero], ['id' => '1-snow-talk']));
        $this->assertSame([], $this->partIds('discussion', [$hero], ['id' => '2-other-talk']));
    }

    #[Test]
    public function the_user_sidebar_only_counts_for_the_page_user(): void
    {
        $sidebar = ['spot' => 'user.sidebar', 'contextType' => 'user', 'contextId' => 4];

        $this->assertSame(['3'], $this->partIds('user', [$sidebar], ['username' => 'member2']));
        $this->assertSame([], $this->partIds('user', [$sidebar], ['username' => 'normal']));
    }

    #[Test]
    public function javascript_only_route_names_match_their_php_route(): void
    {
        $post = ['spot' => 'post.footer', 'contextType' => 'post', 'contextId' => 1];
        $sidebar = ['spot' => 'user.sidebar', 'contextType' => 'user', 'contextId' => 4];

        $this->assertSame(['1'], $this->partIds('discussion.near', [$post], ['id' => '1', 'near' => '4']));
        $this->assertSame(['3'], $this->partIds('user.discussions', [$sidebar], ['username' => 'member2']));
    }

    #[Test]
    public function the_precise_spots_are_offered_and_the_default_route_is_not(): void
    {
        $this->prepareDatabase(['users' => [$this->moderatorUser()], 'group_user' => [$this->moderatorMembership()]]);

        $body = $this->body($this->send($this->request('GET', '/api/scavenger-hunt-spots', ['authenticatedAs' => 3])));
        $spots = array_column($body['data'], 'id');

        foreach (['discussion.hero', 'post.body-end', 'post.footer', 'discussionList.item', 'user.card', 'user.sidebar'] as $key) {
            $this->assertContains($key, $spots);
        }

        $this->assertNotContains('tags.tile', $spots);
        $this->assertNotContains('default', array_column($body['meta']['routes'], 'name'));
    }
}
