<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Search\Completion;

use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use FoF\ScavengerHunt\Completion;
use Illuminate\Database\Eloquent\Builder;

class CompletionSearcher extends AbstractSearcher
{
    public function getQuery(User $actor): Builder
    {
        return Completion::whereVisibleTo($actor)->select('fof_scavenger_completions.*');
    }
}
