<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\User\User;
use FoF\ScavengerHunt\Access\HuntAccess;
use FoF\ScavengerHunt\HuntMap\HuntMapRepository;
use Illuminate\Database\ConnectionInterface;

/**
 * What the frontend needs to decide whether to look for Parts at all. Built from the hunt map
 * plus one count query, and never anything that says where a Part is.
 */
class ForumAttributes
{
    public function __construct(
        protected HuntMapRepository $maps,
        protected HuntAccess $access,
        protected ConnectionInterface $db,
    ) {
    }

    /**
     * @return array<Schema\Attribute>
     */
    public function __invoke(): array
    {
        return [
            Schema\Arr::make('scavengerHunts')
                ->get(fn (object $forum, Context $context) => $this->hunts($context->getActor())),
            Schema\Boolean::make('scavengerHuntCanManage')
                ->get(fn (object $forum, Context $context) => $this->access->canManage($context->getActor())),
        ];
    }

    /**
     * @return list<array{id: int, slug: string, title: string, partCount: int, foundCount: int, canClaim: bool}>
     */
    protected function hunts(User $actor): array
    {
        if (! $this->access->canSee($actor)) {
            return [];
        }

        $map = $this->maps->get();

        $hunts = array_filter(
            $map->hunts,
            fn (array $hunt) => $map->partCount($hunt['id']) > 0 && (! $actor->isGuest() || $hunt['guests_can_see'])
        );

        if ($hunts === []) {
            return [];
        }

        $found = [];

        if (! $actor->isGuest()) {
            $found = $this->db->table('fof_scavenger_finds')
                ->join('fof_scavenger_parts', 'fof_scavenger_parts.id', '=', 'fof_scavenger_finds.part_id')
                ->where('fof_scavenger_finds.user_id', $actor->id)
                ->whereIn('fof_scavenger_parts.hunt_id', array_keys($hunts))
                ->groupBy('fof_scavenger_parts.hunt_id')
                ->select('fof_scavenger_parts.hunt_id')
                ->selectRaw('count(*) as found')
                ->pluck('found', 'hunt_id')
                ->all();
        }

        $canClaim = $this->access->canClaim($actor);

        return array_values(array_map(fn (array $hunt) => [
            'id' => $hunt['id'],
            'slug' => $hunt['slug'],
            'title' => $hunt['title'],
            'partCount' => $map->partCount($hunt['id']),
            'foundCount' => (int) ($found[$hunt['id']] ?? 0),
            'canClaim' => $canClaim,
        ], $hunts));
    }
}
