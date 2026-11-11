<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Api\Controller;

use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use FoF\ScavengerHunt\Hunt;
use FoF\ScavengerHunt\Part;
use FoF\ScavengerHunt\Permission;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

trait LoadsManagedHunt
{
    /**
     * 401 for guests, 404 when the actor can't see the Hunt, 403 when they can't manage Hunts.
     */
    protected function managedHunt(ServerRequestInterface $request, User $actor): Hunt
    {
        $actor->assertRegistered();

        $hunt = Hunt::query()->whereVisibleTo($actor)->findOrFail((int) Arr::get($request->getQueryParams(), 'id'));

        $actor->assertCan(Permission::MANAGE);

        return $hunt;
    }

    /**
     * @param list<int> $ids
     * @return list<Part> in the order asked for
     * @throws ValidationException when a Part isn't in the Hunt
     */
    protected function partsOfHunt(Hunt $hunt, array $ids): array
    {
        $parts = Part::query()->where('hunt_id', $hunt->id)->whereIn('id', $ids)->get()->keyBy('id');

        $ordered = [];

        foreach ($ids as $id) {
            $ordered[] = $parts->get($id) ?? throw new ValidationException([
                'parts' => str_replace(':attribute', 'parts', $this->translator->trans('validation.exists')),
            ]);
        }

        return $ordered;
    }
}
