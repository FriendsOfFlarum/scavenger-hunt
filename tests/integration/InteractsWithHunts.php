<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration;

use Carbon\Carbon;
use Flarum\Group\Group;
use Psr\Http\Message\ResponseInterface;

/**
 * Row builders and request helpers shared by the integration tests.
 *
 * User 1 is the admin created by the test setup, user 2 is `normalUser()`,
 * user 3 is a moderator (and therefore a Hunt manager), user 4 is a second member.
 */
trait InteractsWithHunts
{
    protected function moderatorUser(): array
    {
        return ['id' => 3, 'username' => 'moderator', 'email' => 'moderator@machine.local', 'is_email_confirmed' => 1];
    }

    protected function secondMember(): array
    {
        return ['id' => 4, 'username' => 'member2', 'email' => 'member2@machine.local', 'is_email_confirmed' => 1];
    }

    protected function moderatorMembership(): array
    {
        return ['user_id' => 3, 'group_id' => Group::MODERATOR_ID];
    }

    protected function huntRow(array $overrides = []): array
    {
        $now = Carbon::now()->toDateTimeString();

        return array_merge([
            'id' => 1,
            'slug' => 'snowman',
            'title' => 'Build a Snowman',
            'is_enabled' => true,
            'guests_can_see' => true,
            'starts_at' => null,
            'ends_at' => null,
            'seed' => str_repeat('5eed', 16),
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);
    }

    protected function partRow(array $overrides = []): array
    {
        $now = Carbon::now()->toDateTimeString();

        return array_merge([
            'id' => 1,
            'hunt_id' => 1,
            'name' => 'Carrot nose',
            'mode' => 'fixed',
            'sort' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);
    }

    protected function locationRow(array $overrides = []): array
    {
        $now = Carbon::now()->toDateTimeString();

        return array_merge([
            'id' => 1,
            'part_id' => 1,
            'route' => null,
            'spot' => 'page.corner',
            'context_type' => null,
            'context_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides);
    }

    /**
     * @param list<array{spot: string, contextType?: string, contextId?: int}> $spots
     */
    protected function lookup(?int $userId, string $route, array $spots, array $routeParams = []): ResponseInterface
    {
        $options = ['json' => ['data' => ['attributes' => ['route' => $route, 'routeParams' => $routeParams, 'spots' => $spots]]]];

        if ($userId !== null) {
            $options['authenticatedAs'] = $userId;
        }

        $request = $this->request('POST', '/api/scavenger-hunt-encounters', $options);

        return $this->send($userId === null ? $request->withAttribute('bypassCsrfToken', true) : $request);
    }

    protected function claim(?int $userId, string $token): ResponseInterface
    {
        $options = ['json' => ['data' => ['type' => 'scavenger-hunt-finds', 'attributes' => ['token' => $token]]]];

        if ($userId !== null) {
            $options['authenticatedAs'] = $userId;
        }

        $request = $this->request('POST', '/api/scavenger-hunt-finds', $options);

        return $this->send($userId === null ? $request->withAttribute('bypassCsrfToken', true) : $request);
    }

    protected function body(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true) ?? [];
    }
}
