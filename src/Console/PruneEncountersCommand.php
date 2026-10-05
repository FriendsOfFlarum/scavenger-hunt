<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Console;

use Carbon\Carbon;
use Flarum\Console\AbstractCommand;
use FoF\ScavengerHunt\Encounter;

class PruneEncountersCommand extends AbstractCommand
{
    /**
     * Unclaimed Encounters are kept a week past expiry for the dashboard's "seen" figures;
     * claimed ones are kept for good, because their claim speed is evidence.
     */
    protected const RETENTION_DAYS = 7;

    protected function configure(): void
    {
        $this
            ->setName('fof:scavenger-hunt:prune-encounters')
            ->setDescription('Delete unclaimed scavenger hunt encounters that expired more than a week ago.');
    }

    protected function fire(): int
    {
        $deleted = Encounter::query()
            ->whereNull('claimed_at')
            ->where('expires_at', '<', Carbon::now()->subDays(self::RETENTION_DAYS))
            ->delete();

        $this->info("Deleted $deleted expired encounters.");

        return 0;
    }
}
