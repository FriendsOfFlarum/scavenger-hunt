<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Access;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use FoF\ScavengerHunt\Permission;
use FoF\ScavengerHunt\Setting;

class HuntAccess
{
    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function canManage(User $actor): bool
    {
        return $actor->can(Permission::MANAGE);
    }

    /**
     * Whether the actor is shown Parts at all. Guests are, so a Part can invite them to sign up;
     * Hunt managers are, so they can check their placements.
     */
    public function canSee(User $actor): bool
    {
        return $actor->isGuest() || $this->canManage($actor) || $actor->can(Permission::PLAY);
    }

    /**
     * Hunt managers know every Location, so they only claim when the forum allows it.
     */
    public function canClaim(User $actor): bool
    {
        if ($actor->isGuest() || ! $actor->can(Permission::PLAY)) {
            return false;
        }

        return ! $this->canManage($actor) || (bool) $this->settings->get(Setting::MANAGERS_CAN_PLAY);
    }
}
