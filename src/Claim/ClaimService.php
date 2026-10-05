<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Claim;

use Carbon\Carbon;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use FoF\ScavengerHunt\Access\HuntAccess;
use FoF\ScavengerHunt\Completion;
use FoF\ScavengerHunt\Encounter;
use FoF\ScavengerHunt\Event\HuntCompleted;
use FoF\ScavengerHunt\Event\PartFound;
use FoF\ScavengerHunt\Find;
use FoF\ScavengerHunt\HuntMap\HuntMapRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

class ClaimService
{
    public function __construct(
        protected ConnectionInterface $db,
        protected HuntAccess $access,
        protected HuntMapRepository $maps,
        protected Dispatcher $events,
        protected TranslatorInterface $translator,
    ) {
    }

    /**
     * Turns a Claim token into a Find, and into a Completion when it was the Hunt's last Part.
     *
     * Every way a token can be wrong — unknown, someone else's, used, expired, or its Hunt has
     * ended — fails identically, so a failed claim tells a prober nothing.
     */
    public function claim(User $actor, string $token): Find
    {
        if (! $this->access->canClaim($actor)) {
            throw new PermissionDeniedException();
        }

        $now = Carbon::now();
        $encounter = $token === '' ? null : Encounter::query()->where('token_hash', hash('sha256', $token))->first();
        $map = $this->maps->get();
        $part = $encounter ? $map->part($encounter->part_id) : null;

        if (! $encounter || ! $part || $encounter->user_id !== $actor->id) {
            throw $this->failed();
        }

        /** @var array{0: Find, 1: bool} $result */
        $result = $this->db->transaction(function () use ($actor, $encounter, $now) {
            // The conditional update is what makes a token single-use: of two simultaneous claims,
            // only one can flip claimed_at from null.
            $claimed = Encounter::query()
                ->whereKey($encounter->id)
                ->whereNull('claimed_at')
                ->where('expires_at', '>', $now)
                ->update([
                    'claimed_at' => $now,
                    'claim_ms' => max(0, $now->getTimestampMs() - $encounter->issued_at_ms),
                ]);

            if ($claimed === 0) {
                throw $this->failed();
            }

            // insertOrIgnore rather than catching a duplicate key: a failed statement aborts the
            // whole transaction on PostgreSQL.
            $isNew = Find::query()->insertOrIgnore([
                'part_id' => $encounter->part_id,
                'user_id' => $actor->id,
                'found_at' => $now,
            ]) > 0;

            $find = Find::query()->where('part_id', $encounter->part_id)->where('user_id', $actor->id)->first();

            if ($find === null) {
                throw $this->failed();
            }

            return [$find, $isNew];
        });

        [$find, $isNew] = $result;

        if (! $isNew) {
            return $find;
        }

        $this->events->dispatch(new PartFound($find, $actor));

        if ($completion = $this->completeIfFinished($actor, $part['hunt_id'], $map->partIdsOf($part['hunt_id']), $now)) {
            $this->events->dispatch(new HuntCompleted($completion, $actor));
        }

        return $find;
    }

    /**
     * Runs after the Find has committed, so of two claims for a member's last two Parts, the one
     * that commits second always sees both Finds. insertOrIgnore keeps the Completion single.
     *
     * @param list<int> $partIds
     */
    protected function completeIfFinished(User $actor, int $huntId, array $partIds, Carbon $now): ?Completion
    {
        $found = Find::query()->where('user_id', $actor->id)->whereIn('part_id', $partIds)->count();

        if ($found < count($partIds)) {
            return null;
        }

        $completedNow = Completion::query()->insertOrIgnore([
            'hunt_id' => $huntId,
            'user_id' => $actor->id,
            'completed_at' => $now,
        ]) > 0;

        return $completedNow
            ? Completion::query()->where('hunt_id', $huntId)->where('user_id', $actor->id)->first()
            : null;
    }

    protected function failed(): ValidationException
    {
        return new ValidationException(['token' => $this->translator->trans('fof-scavenger-hunt.api.claim_failed')]);
    }
}
