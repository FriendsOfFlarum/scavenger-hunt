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
use Flarum\Locale\TranslatorInterface;
use FoF\ScavengerHunt\HidingSpot\HidingSpotRegistry;
use FoF\ScavengerHunt\Location;

/**
 * Checks a Location against the spot registry: the spot is registered, the route is a page the
 * spot appears on, and the item matches the spot's context and exists. Fills in the context type.
 */
class LocationValidator
{
    public function __construct(
        protected HidingSpotRegistry $spots,
        protected TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws ValidationException keyed by the API field name (`spot`, `route`, `contextType`, `contextId`)
     */
    public function validate(Location $location): void
    {
        $spot = $this->spots->get((string) $location->spot) ?? throw $this->invalid('spot', 'spot_unknown');

        if ($location->route !== null && (! $this->spots->isPageRoute($location->route) || ! $spot->allowsRoute($location->route))) {
            throw $this->invalid('route', 'route_not_allowed');
        }

        if ($spot->context === null) {
            if ($location->context_type !== null || $location->context_id !== null) {
                throw $this->invalid('contextType', 'context_mismatch');
            }

            return;
        }

        if ($location->context_type !== null && $location->context_type !== $spot->context) {
            throw $this->invalid('contextType', 'context_mismatch');
        }

        $location->context_type = $spot->context;

        if ($location->context_id !== null) {
            $class = $this->spots->contextModel($spot->context);

            if ($class === null || ! $class::query()->whereKey($location->context_id)->exists()) {
                throw $this->invalid('contextId', 'context_not_found');
            }
        }
    }

    protected function invalid(string $field, string $key): ValidationException
    {
        return new ValidationException([$field => $this->translator->trans("fof-scavenger-hunt.api.$key")]);
    }
}
