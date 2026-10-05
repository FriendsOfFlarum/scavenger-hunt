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
use Flarum\Database\ScopeVisibilityTrait;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $hunt_id
 * @property int $user_id
 * @property Carbon $completed_at
 * @property-read Hunt $hunt
 * @property-read User $user
 */
class Completion extends AbstractModel
{
    use ScopeVisibilityTrait;

    protected $table = 'fof_scavenger_completions';

    protected $casts = [
        'hunt_id' => 'integer',
        'user_id' => 'integer',
        'completed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Hunt, $this>
     */
    public function hunt(): BelongsTo
    {
        return $this->belongsTo(Hunt::class, 'hunt_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
