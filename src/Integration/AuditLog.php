<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Integration;

use Flarum\Audit\Extend\Audit;
use FoF\ScavengerHunt\Event;

/**
 * What flarum/audit records: everything a Hunt manager changes, every Completion (with the IP
 * audit adds, which helps when checking prize winners) and crossings of the daily lookup limit.
 * Individual Finds are left out; there are far too many to be useful.
 */
class AuditLog
{
    public static function extender(): Audit
    {
        $hunt = fn (Event\HuntCreated|Event\HuntUpdated|Event\HuntDeleted $e) => ['hunt_id' => $e->hunt->id, 'title' => $e->hunt->title];
        $part = fn (Event\PartCreated|Event\PartUpdated|Event\PartDeleted $e) => ['hunt_id' => $e->part->hunt_id, 'part_id' => $e->part->id, 'name' => $e->part->name];
        $location = fn (Event\LocationCreated|Event\LocationUpdated|Event\LocationDeleted $e) => ['part_id' => $e->location->part_id, 'location_id' => $e->location->id, 'spot' => $e->location->spot];

        return (new Audit())
            ->listen(Event\HuntCreated::class, 'fof-scavenger-hunt.hunt_created', $hunt)
            ->listen(Event\HuntUpdated::class, 'fof-scavenger-hunt.hunt_updated', $hunt)
            ->listen(Event\HuntDeleted::class, 'fof-scavenger-hunt.hunt_deleted', $hunt)
            ->listen(Event\PartCreated::class, 'fof-scavenger-hunt.part_created', $part)
            ->listen(Event\PartUpdated::class, 'fof-scavenger-hunt.part_updated', $part)
            ->listen(Event\PartDeleted::class, 'fof-scavenger-hunt.part_deleted', $part)
            ->listen(Event\LocationCreated::class, 'fof-scavenger-hunt.location_created', $location)
            ->listen(Event\LocationUpdated::class, 'fof-scavenger-hunt.location_updated', $location)
            ->listen(Event\LocationDeleted::class, 'fof-scavenger-hunt.location_deleted', $location)
            ->listen(Event\ProgressReset::class, 'fof-scavenger-hunt.progress_reset', fn (Event\ProgressReset $e) => ['hunt_id' => $e->hunt->id, 'user_id' => $e->user->id])
            ->listen(Event\HuntCompleted::class, 'fof-scavenger-hunt.hunt_completed', fn (Event\HuntCompleted $e) => ['hunt_id' => $e->completion->hunt_id, 'user_id' => $e->completion->user_id])
            ->listen(Event\LookupLimitReached::class, 'fof-scavenger-hunt.lookup_limit_reached', fn (Event\LookupLimitReached $e) => ['user_id' => $e->actor->id]);
    }
}
