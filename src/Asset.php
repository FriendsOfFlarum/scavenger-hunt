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

use Carbon\Carbon;
use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property string $path
 * @property string $name
 * @property string $mime_type
 * @property int|null $width
 * @property int|null $height
 * @property int $size
 * @property int|null $user_id
 * @property Carbon $created_at
 */
class Asset extends AbstractModel
{
    protected $table = 'fof_scavenger_assets';

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'size' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function reference(): string
    {
        return "asset:$this->id";
    }
}
