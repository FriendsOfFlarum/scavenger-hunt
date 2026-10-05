<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt;

final class Setting
{
    public const TOKEN_LIFETIME = 'fof-scavenger-hunt.tokenLifetime';
    public const MAX_PARTS_PER_PAGE = 'fof-scavenger-hunt.maxPartsPerPage';
    public const LOOKUP_RATE_LIMIT = 'fof-scavenger-hunt.lookupRateLimit';
    public const DAILY_LOOKUP_LIMIT = 'fof-scavenger-hunt.dailyLookupLimit';
    public const MANAGERS_CAN_PLAY = 'fof-scavenger-hunt.managersCanPlay';
    public const SHOW_HEADER_INDICATOR = 'fof-scavenger-hunt.showHeaderIndicator';
}
