<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Search\Find;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * @implements FilterInterface<DatabaseSearchState>
 */
class HuntFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'hunt';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $huntId = $this->asInt($value);

        $state->getQuery()->{$negate ? 'whereNotIn' : 'whereIn'}('fof_scavenger_finds.part_id', function ($query) use ($huntId) {
            $query->select('id')->from('fof_scavenger_parts')->where('hunt_id', $huntId);
        });
    }
}
