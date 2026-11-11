<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Placement;

use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\User\User;

final class Candidates
{
    /**
     * @param list<Discussion> $discussions newest activity first
     * @param list<Post> $posts comment posts of those discussions
     * @param list<User> $users
     * @param list<AbstractModel> $tags
     */
    public function __construct(
        public readonly array $discussions,
        public readonly array $posts,
        public readonly array $users,
        public readonly array $tags,
    ) {
    }
}
