<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\placement;

use Flarum\Discussion\Discussion;
use Flarum\Foundation\ValidationException;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\ScavengerHunt\Location;
use FoF\ScavengerHunt\Placement\LocationValidator;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use FoF\ScavengerHunt\Tests\integration\SeedsForumContent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class LocationValidatorTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;
    use SeedsForumContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');

        $this->prepareDatabase([
            'users' => [$this->normalUser()],
            Discussion::class => [$this->discussionRow(1)],
            Post::class => [$this->postRow(1, 1, 1)],
        ]);
    }

    protected function location(array $attributes): Location
    {
        $location = new Location();

        foreach (array_merge(['route' => null, 'context_type' => null, 'context_id' => null], $attributes) as $key => $value) {
            $location->setAttribute($key, $value);
        }

        return $location;
    }

    protected function validate(Location $location): ?string
    {
        try {
            $this->app()->getContainer()->make(LocationValidator::class)->validate($location);
        } catch (ValidationException $e) {
            return (string) array_key_first($e->getAttributes());
        }

        return null;
    }

    public static function invalidLocations(): array
    {
        return [
            'unregistered spot' => [['spot' => 'nope'], 'spot'],
            'route not a page' => [['spot' => 'page.corner', 'route' => 'logoutPage'], 'route'],
            'route the spot is not on' => [['spot' => 'post.footer', 'route' => 'index'], 'route'],
            'item on a page-level spot' => [['spot' => 'page.corner', 'context_type' => 'post', 'context_id' => 1], 'contextType'],
            'item of the wrong kind' => [['spot' => 'post.footer', 'context_type' => 'user', 'context_id' => 2], 'contextType'],
            'missing item' => [['spot' => 'post.footer', 'context_id' => 999], 'contextId'],
        ];
    }

    #[Test]
    #[DataProvider('invalidLocations')]
    public function invalid_locations_are_rejected_by_field(array $attributes, string $field): void
    {
        $this->assertSame($field, $this->validate($this->location($attributes)));
    }

    #[Test]
    public function a_valid_location_gets_its_spots_context_type(): void
    {
        $location = $this->location(['spot' => 'post.footer', 'route' => 'discussion', 'context_id' => 1]);

        $this->assertNull($this->validate($location));
        $this->assertSame('post', $location->context_type);
    }
}
