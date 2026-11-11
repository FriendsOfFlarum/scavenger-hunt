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

use Flarum\User\Guest;
use Flarum\User\User;
use FoF\ScavengerHunt\Hunt;
use Illuminate\Database\Eloquent\Collection;

/**
 * The actor a Hunt's hiding places must be visible to: a guest when guests can see the Hunt,
 * otherwise a plain member with no groups beyond Member.
 */
class HuntAudience
{
    public function for(Hunt $hunt): User
    {
        return $hunt->guests_can_see ? new Guest() : $this->member();
    }

    protected function member(): User
    {
        $member = new User();

        // Never saved. Visibility scopes compare `user_id` with the actor's id, and a null id would
        // become `user_id IS NULL` and match authorless content. Not 0 either: that is the guest's
        // id, and policies cache verdicts per actor id.
        $member->id = -1;
        $member->is_email_confirmed = true;

        // Member is implied by a confirmed email (User::permissionGroupIds), so no explicit groups.
        $member->setRelation('groups', new Collection());

        return $member;
    }
}
