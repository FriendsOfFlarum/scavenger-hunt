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

use Flarum\Extend;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/resources/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/resources/less/admin.less'),

    new Extend\Locales(__DIR__.'/resources/locale'),

    // AbstractModel takes defaults from this registry, not from Eloquent's $attributes.
    (new Extend\Model(Hunt::class))
        ->default('is_enabled', false)
        ->default('guests_can_see', true),

    (new Extend\Model(Part::class))
        ->default('mode', PlacementMode::Fixed->value)
        ->default('sort', 0),

    (new Extend\ModelVisibility(Hunt::class))
        ->scope(Access\ScopeHuntVisibility::class),

    new Extend\ApiResource(Api\Resource\HuntResource::class),
];
