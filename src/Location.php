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
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $part_id
 * @property string|null $route
 * @property string $spot
 * @property string|null $context_type
 * @property int|null $context_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Part $part
 */
class Location extends AbstractModel
{
    use ScopeVisibilityTrait;

    protected $table = 'fof_scavenger_locations';

    public $timestamps = true;

    protected $casts = [
        'part_id' => 'integer',
        'context_id' => 'integer',
    ];

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'part_id');
    }
}
