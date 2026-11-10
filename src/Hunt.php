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
use Flarum\Group\Group;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property string|null $template
 * @property string|null $image
 * @property string|null $completed_image
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_enabled
 * @property bool $guests_can_see
 * @property int|null $reward_group_id
 * @property string|null $reward_message
 * @property string $seed
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Part> $parts
 * @property-read Group|null $rewardGroup
 * @property-read Collection<int, Completion> $completions
 */
class Hunt extends AbstractModel
{
    use ScopeVisibilityTrait;

    protected $table = 'fof_scavenger_hunts';

    public $timestamps = true;

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_enabled' => 'boolean',
        'guests_can_see' => 'boolean',
        'reward_group_id' => 'integer',
    ];

    /**
     * @return HasMany<Part, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(Part::class, 'hunt_id')->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<Completion, $this>
     */
    public function completions(): HasMany
    {
        return $this->hasMany(Completion::class, 'hunt_id');
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function rewardGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'reward_group_id');
    }

    public function hasStarted(?Carbon $now = null): bool
    {
        $now ??= Carbon::now();

        return $this->starts_at === null || $this->starts_at->lte($now);
    }

    public function isActive(?Carbon $now = null): bool
    {
        $now ??= Carbon::now();

        return $this->is_enabled
            && $this->hasStarted($now)
            && ($this->ends_at === null || $this->ends_at->gt($now));
    }
}
