<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use FoF\ScavengerHunt\Access\HuntAccess;
use FoF\ScavengerHunt\Event;
use FoF\ScavengerHunt\Image\ImageReference;
use FoF\ScavengerHunt\Part;
use FoF\ScavengerHunt\Permission;
use FoF\ScavengerHunt\PlacementMode;
use FoF\ScavengerHunt\Progress\ActorProgress;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * @extends AbstractDatabaseResource<Part>
 */
class PartResource extends AbstractDatabaseResource
{
    public function __construct(
        protected HuntAccess $access,
        protected ImageReference $images,
        protected ActorProgress $progress,
        protected TranslatorInterface $translator,
    ) {
    }

    public function type(): string
    {
        return 'scavenger-hunt-parts';
    }

    public function model(): string
    {
        return Part::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $query->whereVisibleTo($context->getActor());
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Show::make(),
            Endpoint\Create::make()
                ->authenticated()
                ->can(Permission::MANAGE),
            Endpoint\Update::make()
                ->authenticated()
                ->can(Permission::MANAGE),
            Endpoint\Delete::make()
                ->authenticated()
                ->can(Permission::MANAGE),
        ];
    }

    public function fields(): array
    {
        $manages = fn (Part $part, Context $context) => $this->access->canManage($context->getActor());
        $registered = fn (Part $part, Context $context) => ! $context->getActor()->isGuest();

        return [
            Schema\Str::make('name')
                ->requiredOnCreate()
                ->writable()
                // minLength(1) alone lets an empty string through: Laravel skips most rules for empty values.
                ->rule('filled')
                ->maxLength(100),
            // Random Parts never carry a Hint: nobody can be told where they are.
            Schema\Str::make('hint')
                ->nullable()
                ->writable()
                ->maxLength(255)
                ->visible(fn (Part $part) => $part->mode === PlacementMode::Fixed),
            Schema\Str::make('imageUrl')
                ->nullable()
                ->get(fn (Part $part, Context $context) => $this->images->url($part->image, $context)),
            Schema\Arr::make('layer')
                ->nullable()
                ->writable(),
            Schema\Integer::make('sort')
                ->writable()
                ->min(0)
                ->max(65535),
            Schema\Boolean::make('isRandom')
                ->get(fn (Part $part) => $part->mode->isRandom()),
            Schema\Boolean::make('isFound')
                ->visible($registered)
                ->get(fn (Part $part, Context $context) => $this->progress->isFound($context, $part->hunt_id, $part->id)),
            Schema\DateTime::make('foundAt')
                ->nullable()
                ->visible($registered)
                ->get(fn (Part $part, Context $context) => $this->progress->foundAt($context, $part->hunt_id, $part->id)),

            Schema\Str::make('mode')
                ->visible($manages)
                ->writable()
                ->in(array_map(fn (PlacementMode $mode) => $mode->value, PlacementMode::cases()))
                ->get(fn (Part $part) => $part->mode->value)
                ->set(fn (Part $part, string $value) => $part->mode = PlacementMode::from($value)),
            Schema\Integer::make('wanderChance')
                ->nullable()
                ->visible($manages)
                ->writable()
                ->min(1)
                ->max(100),
            Schema\Str::make('image')
                ->nullable()
                ->visible($manages)
                ->writable()
                ->maxLength(255),

            Schema\Relationship\ToOne::make('hunt')
                ->type('scavenger-hunts')
                ->includable()
                ->writableOnCreate()
                ->requiredOnCreate(),
            Schema\Relationship\ToMany::make('locations')
                ->type('scavenger-hunt-locations')
                ->includable()
                ->visible($manages),
        ];
    }

    public function saving(object $model, OriginalContext $context): ?object
    {
        if ($model->mode->isRandom()) {
            if ($model->isDirty('hint') && filled($model->hint)) {
                throw $this->invalid('hint', 'hint_requires_fixed');
            }

            $model->hint = null;
        }

        if ($model->mode === PlacementMode::Wandering) {
            if ($model->wander_chance === null) {
                throw $this->invalid('wanderChance', 'wander_chance_required');
            }
        } else {
            $model->wander_chance = null;
        }

        if (! $this->images->isValid($model->image)) {
            throw $this->invalid('image', 'image_invalid');
        }

        if ($model->layer !== null) {
            $model->layer = $this->normaliseLayer($model->layer) ?? throw $this->invalid('layer', 'layer_invalid');
        }

        return $model;
    }

    public function created(object $model, OriginalContext $context): ?object
    {
        $this->events->dispatch(new Event\PartCreated($model, $context->getActor()));

        return $model;
    }

    public function updated(object $model, OriginalContext $context): ?object
    {
        $this->events->dispatch(new Event\PartUpdated($model, $context->getActor()));

        return $model;
    }

    public function deleted(object $model, OriginalContext $context): void
    {
        $this->events->dispatch(new Event\PartDeleted($model, $context->getActor()));
    }

    /**
     * @param array<string, mixed> $layer
     * @return array{x: float, y: float, width: float, z: int}|null
     */
    protected function normaliseLayer(array $layer): ?array
    {
        foreach (['x', 'y', 'width', 'z'] as $key) {
            if (! isset($layer[$key]) || ! is_numeric($layer[$key])) {
                return null;
            }
        }

        $normalised = [
            'x' => (float) $layer['x'],
            'y' => (float) $layer['y'],
            'width' => (float) $layer['width'],
            'z' => (int) $layer['z'],
        ];

        $inRange = $normalised['x'] >= 0 && $normalised['x'] <= 100
            && $normalised['y'] >= 0 && $normalised['y'] <= 100
            && $normalised['width'] > 0 && $normalised['width'] <= 100
            && $normalised['z'] >= 0 && $normalised['z'] <= 1000;

        return $inRange ? $normalised : null;
    }

    protected function invalid(string $field, string $key): ValidationException
    {
        return new ValidationException([$field => $this->translator->trans("fof-scavenger-hunt.api.$key")]);
    }
}
