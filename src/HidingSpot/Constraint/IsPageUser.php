<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\HidingSpot\Constraint;

use Flarum\Http\SlugManager;
use Flarum\User\Guest;
use Flarum\User\User;
use FoF\ScavengerHunt\HidingSpot\ReportedPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * The reported user must be the one whose profile it was reported on. The `username` route
 * parameter goes through the slug manager, since forums can address users by id instead.
 */
class IsPageUser
{
    public function __construct(
        protected SlugManager $slugs
    ) {
    }

    public function __invoke(Builder $query, ReportedPage $page): void
    {
        $userId = 0;
        $slug = $page->params['username'] ?? '';

        if ($slug !== '') {
            try {
                $userId = (int) $this->slugs->forResource(User::class)->fromSlug($slug, $page->actor ?? new Guest())->id;
            } catch (ModelNotFoundException) {
                $userId = 0;
            }
        }

        $query->where('users.id', $userId);
    }
}
