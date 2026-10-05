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
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $part_id
 * @property int $user_id
 * @property int $location_id
 * @property string $token_hash
 * @property Carbon $created_at
 * @property int $issued_at_ms
 * @property Carbon $expires_at
 * @property Carbon|null $claimed_at
 * @property int|null $claim_ms
 * @property-read Part $part
 * @property-read User $user
 * @property-read Location $location
 */
class Encounter extends AbstractModel
{
    protected $table = 'fof_scavenger_encounters';

    protected $casts = [
        'part_id' => 'integer',
        'user_id' => 'integer',
        'location_id' => 'integer',
        'created_at' => 'datetime',
        'issued_at_ms' => 'integer',
        'expires_at' => 'datetime',
        'claimed_at' => 'datetime',
        'claim_ms' => 'integer',
    ];

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'part_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
