<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Data;

use Flarum\Gdpr\Data\Type;
use FoF\ScavengerHunt\Completion;
use FoF\ScavengerHunt\Encounter;
use FoF\ScavengerHunt\Find;

/**
 * Anonymizing keeps Finds and Completions, so First finishers stays intact under the anonymized account.
 */
class ScavengerHunt extends Type
{
    /**
     * @return array<string, string>
     */
    #[\Override]
    public function export(): ?array
    {
        $finds = Find::query()
            ->where('user_id', $this->user->id)
            ->with('part.hunt')
            ->orderBy('found_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Find $find) => [
                'hunt' => $find->part->hunt->title,
                'part' => $find->part->name,
                'found_at' => $find->found_at->toIso8601String(),
            ]);

        $completions = Completion::query()
            ->where('user_id', $this->user->id)
            ->with('hunt')
            ->orderBy('completed_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Completion $completion) => [
                'hunt' => $completion->hunt->title,
                'completed_at' => $completion->completed_at->toIso8601String(),
            ]);

        $encounters = Encounter::query()
            ->where('user_id', $this->user->id)
            ->with('part.hunt')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (Encounter $encounter) => [
                'hunt' => $encounter->part->hunt->title,
                'part' => $encounter->part->name,
                'shown_at' => $encounter->created_at->toIso8601String(),
                'claimed_at' => $encounter->claimed_at?->toIso8601String(),
            ]);

        return [
            'scavenger-hunt/finds.json' => $this->encodeForExport($finds->all()),
            'scavenger-hunt/completions.json' => $this->encodeForExport($completions->all()),
            'scavenger-hunt/encounters.json' => $this->encodeForExport($encounters->all()),
        ];
    }

    #[\Override]
    public function anonymize(): void
    {
        Encounter::query()->where('user_id', $this->user->id)->delete();
    }

    #[\Override]
    public function delete(): void
    {
        Encounter::query()->where('user_id', $this->user->id)->delete();
        Find::query()->where('user_id', $this->user->id)->delete();
        Completion::query()->where('user_id', $this->user->id)->delete();
    }
}
