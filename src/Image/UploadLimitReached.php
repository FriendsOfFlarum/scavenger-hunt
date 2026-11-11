<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Image;

use Exception;
use Flarum\Foundation\KnownError;

/**
 * Someone other than an admin has uploaded as many Assets as an hour allows.
 */
class UploadLimitReached extends Exception implements KnownError
{
    public const TYPE = 'scavenger_hunt_upload_limit';

    public function getType(): string
    {
        return self::TYPE;
    }
}
