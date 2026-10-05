<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tracking;

use Flarum\User\User;
use FoF\ScavengerHunt\Completion;
use FoF\ScavengerHunt\Encounter;
use FoF\ScavengerHunt\Event\ProgressReset;
use FoF\ScavengerHunt\Find;
use FoF\ScavengerHunt\Hunt;
use FoF\ScavengerHunt\Part;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

/**
 * Removes a member's Finds, Completion and open Encounters in one Hunt, e.g. after cheating.
 * Claimed Encounters stay as evidence; the Reward group, if any, is left for a manager to remove.
 */
class ProgressResetter
{
    public function __construct(
        protected ConnectionInterface $db,
        protected Dispatcher $events,
    ) {
    }

    public function reset(Hunt $hunt, User $user, User $actor): void
    {
        $partIds = Part::query()->where('hunt_id', $hunt->id)->pluck('id')->all();

        $this->db->transaction(function () use ($hunt, $user, $partIds) {
            Find::query()->where('user_id', $user->id)->whereIn('part_id', $partIds)->delete();
            Encounter::query()->where('user_id', $user->id)->whereIn('part_id', $partIds)->whereNull('claimed_at')->delete();
            Completion::query()->where('user_id', $user->id)->where('hunt_id', $hunt->id)->delete();
        });

        $this->events->dispatch(new ProgressReset($hunt, $user, $actor));
    }
}
