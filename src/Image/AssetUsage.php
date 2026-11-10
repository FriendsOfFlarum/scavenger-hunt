<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Image;

use Closure;
use Flarum\Api\Context;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use WeakMap;

/**
 * Which Assets a Hunt or Part points at. `isInUse()` defers its answer until the whole response
 * has asked, then checks every Asset on it in one query. State is keyed by the actor, which
 * Flarum builds afresh for every request (see ActorProgress).
 */
class AssetUsage
{
    /**
     * @var WeakMap<User, array{pending: array<int, true>, inUse: array<int, bool>}>
     */
    protected WeakMap $memo;

    public function __construct(
        protected ConnectionInterface $db
    ) {
        $this->memo = new WeakMap();
    }

    /**
     * @return Closure(): bool
     */
    public function isInUse(Context $context, int $assetId): Closure
    {
        $actor = $context->getActor();
        $state = $this->memo[$actor] ?? ['pending' => [], 'inUse' => []];

        if (! isset($state['inUse'][$assetId])) {
            $state['pending'][$assetId] = true;
        }

        $this->memo[$actor] = $state;

        return function () use ($actor, $assetId): bool {
            $state = $this->memo[$actor];

            if ($state['pending']) {
                $used = $this->inUse(array_keys($state['pending']));

                foreach (array_keys($state['pending']) as $id) {
                    $state['inUse'][$id] = in_array($id, $used, true);
                }

                $state['pending'] = [];
                $this->memo[$actor] = $state;
            }

            return $state['inUse'][$assetId] ?? false;
        };
    }

    /**
     * @param list<int> $assetIds
     * @return list<int> the ones a Hunt or Part references
     */
    public function inUse(array $assetIds): array
    {
        if (! $assetIds) {
            return [];
        }

        $references = array_map(fn (int $id) => "asset:$id", $assetIds);

        $used = $this->db->table('fof_scavenger_hunts')->whereIn('image', $references)->select('image as reference')
            ->union($this->db->table('fof_scavenger_hunts')->whereIn('completed_image', $references)->select('completed_image as reference'))
            ->union($this->db->table('fof_scavenger_parts')->whereIn('image', $references)->select('image as reference'))
            ->pluck('reference');

        return array_values(array_map(fn (string $reference) => (int) substr($reference, strlen('asset:')), $used->all()));
    }
}
