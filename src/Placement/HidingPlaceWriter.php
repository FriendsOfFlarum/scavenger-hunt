<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Placement;

use Flarum\Foundation\ValidationException;
use Flarum\User\User;
use FoF\ScavengerHunt\Event\LocationCreated;
use FoF\ScavengerHunt\Event\LocationDeleted;
use FoF\ScavengerHunt\HuntMap\HuntMapRepository;
use FoF\ScavengerHunt\Location;
use FoF\ScavengerHunt\Part;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

/**
 * Replaces the Locations of several Parts at once: all of them or none.
 */
class HidingPlaceWriter
{
    public function __construct(
        protected ConnectionInterface $db,
        protected Dispatcher $events,
        protected LocationValidator $validator,
        protected HuntMapRepository $huntMap,
    ) {
    }

    /**
     * @param list<array{part: Part, locations: list<array{spot: string, route: string|null, contextType: string|null, contextId: int|null}>}> $replacements
     * @throws ValidationException keyed `parts.<i>.locations.<j>.<field>`, with nothing saved
     */
    public function replace(array $replacements, User $actor): void
    {
        [$deleted, $created] = $this->db->transaction(function () use ($replacements) {
            $new = [];

            foreach ($replacements as $i => $replacement) {
                foreach ($replacement['locations'] as $j => $attributes) {
                    $location = new Location();
                    $location->part_id = $replacement['part']->id;
                    $location->spot = $attributes['spot'];
                    $location->route = $attributes['route'];
                    $location->context_type = $attributes['contextType'];
                    $location->context_id = $attributes['contextId'];

                    try {
                        $this->validator->validate($location);
                    } catch (ValidationException $e) {
                        $errors = [];

                        foreach ($e->getAttributes() as $field => $message) {
                            $errors["parts.$i.locations.$j.$field"] = $message;
                        }

                        throw new ValidationException($errors);
                    }

                    $new[] = $location;
                }
            }

            $partIds = array_map(fn (array $replacement) => $replacement['part']->id, $replacements);
            $old = Location::query()->whereIn('part_id', $partIds)->orderBy('id')->get()->all();

            foreach ($old as $location) {
                $location->delete();
            }

            foreach ($new as $location) {
                $location->save();
            }

            return [$old, $new];
        });

        // After the commit, so a hunt map rebuilt in between can't capture the old Locations
        // under the new generation.
        $this->huntMap->forgetOnce(function () use ($deleted, $created, $actor) {
            foreach ($deleted as $location) {
                $this->events->dispatch(new LocationDeleted($location, $actor));
            }

            foreach ($created as $location) {
                $this->events->dispatch(new LocationCreated($location, $actor));
            }
        });
    }
}
