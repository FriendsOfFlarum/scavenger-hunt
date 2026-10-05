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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $hunt_id
 * @property string $name
 * @property string|null $hint
 * @property string|null $image
 * @property array{x: int|float, y: int|float, width: int|float, z: int}|null $layer
 * @property int $sort
 * @property PlacementMode $mode
 * @property int|null $wander_chance
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Hunt $hunt
 * @property-read Collection<int, Location> $locations
 * @property-read Collection<int, Find> $finds
 */
class Part extends AbstractModel
{
    use ScopeVisibilityTrait;

    protected $table = 'fof_scavenger_parts';

    public $timestamps = true;

    protected $attributes = [
        'mode' => 'fixed',
        'sort' => 0,
    ];

    protected $casts = [
        'hunt_id' => 'integer',
        'layer' => 'array',
        'sort' => 'integer',
        'mode' => PlacementMode::class,
        'wander_chance' => 'integer',
    ];

    /**
     * @return BelongsTo<Hunt, $this>
     */
    public function hunt(): BelongsTo
    {
        return $this->belongsTo(Hunt::class, 'hunt_id');
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class, 'part_id')->orderBy('id');
    }

    /**
     * @return HasMany<Find, $this>
     */
    public function finds(): HasMany
    {
        return $this->hasMany(Find::class, 'part_id');
    }
}
