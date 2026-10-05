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
use Flarum\Api\Sort\SortColumn;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use FoF\ScavengerHunt\Access\HuntAccess;
use FoF\ScavengerHunt\Event;
use FoF\ScavengerHunt\Hunt;
use FoF\ScavengerHunt\Image\ImageReference;
use FoF\ScavengerHunt\Permission;
use FoF\ScavengerHunt\Progress\ActorProgress;
use FoF\ScavengerHunt\Template\TemplateRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * @extends AbstractDatabaseResource<Hunt>
 */
class HuntResource extends AbstractDatabaseResource
{
    public function __construct(
        protected HuntAccess $access,
        protected ImageReference $images,
        protected ActorProgress $progress,
        protected TranslatorInterface $translator,
        protected TemplateRepository $templates,
    ) {
    }

    public function type(): string
    {
        return 'scavenger-hunts';
    }

    public function model(): string
    {
        return Hunt::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $query->whereVisibleTo($context->getActor());
    }

    /**
     * Hunt pages are addressed by slug, everything else by id.
     */
    public function find(string $id, OriginalContext $context): ?object
    {
        $query = $this->query($context);

        return ctype_digit($id) ? $query->find($id) : $query->where('slug', $id)->first();
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->defaultSort('-createdAt')
                ->paginate(20, 50),
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
        $manages = fn (Hunt $hunt, Context $context) => $this->access->canManage($context->getActor());
        $registered = fn (Hunt $hunt, Context $context) => ! $context->getActor()->isGuest();

        return [
            Schema\Str::make('title')
                ->requiredOnCreate()
                ->writable()
                ->minLength(1)
                ->maxLength(200),
            Schema\Str::make('slug')
                ->writable()
                ->maxLength(100)
                ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                ->unique('fof_scavenger_hunts', 'slug', true),
            Schema\Str::make('description')
                ->nullable()
                ->writable()
                ->maxLength(5000),
            Schema\DateTime::make('startsAt')
                ->nullable()
                ->writable(),
            Schema\DateTime::make('endsAt')
                ->nullable()
                ->writable(),
            Schema\Boolean::make('isActive')
                ->get(fn (Hunt $hunt) => $hunt->isActive()),
            Schema\Str::make('imageUrl')
                ->nullable()
                ->get(fn (Hunt $hunt) => $this->images->url($hunt->image)),
            Schema\Str::make('completedImageUrl')
                ->nullable()
                ->get(fn (Hunt $hunt) => $this->images->url($hunt->completed_image)),
            Schema\Integer::make('partCount')
                ->countRelation('parts'),
            Schema\Integer::make('foundCount')
                ->visible($registered)
                ->get(fn (Hunt $hunt, Context $context) => $this->progress->foundCount($context, $hunt->id)),
            Schema\DateTime::make('completedAt')
                ->nullable()
                ->visible($registered)
                ->get(fn (Hunt $hunt, Context $context) => $this->progress->completedAt($context, $hunt->id)),

            Schema\Boolean::make('isEnabled')
                ->visible($manages)
                ->writable(),
            Schema\Boolean::make('guestsCanSee')
                ->visible($manages)
                ->writable(),
            Schema\Str::make('image')
                ->nullable()
                ->visible($manages)
                ->writable()
                ->maxLength(255),
            Schema\Str::make('completedImage')
                ->nullable()
                ->visible($manages)
                ->writable()
                ->maxLength(255),
            Schema\Str::make('template')
                ->nullable()
                ->visible($manages)
                ->writableOnCreate()
                ->maxLength(50)
                ->set(function (Hunt $hunt, ?string $key) {
                    if ($key === null) {
                        return;
                    }

                    $template = $this->templates->get($key);

                    if (! $template) {
                        throw new ValidationException(['template' => $this->translator->trans('fof-scavenger-hunt.api.template_unknown')]);
                    }

                    $hunt->template = $template->key;
                    $hunt->image ??= $template->reference($template->image);
                    $hunt->completed_image ??= $template->reference($template->completedImage);
                    $hunt->afterSave(fn (Hunt $hunt) => $this->templates->copyParts($template, $hunt));
                }),

            Schema\Relationship\ToMany::make('parts')
                ->type('scavenger-hunt-parts')
                ->includable(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt'),
            SortColumn::make('startsAt'),
            SortColumn::make('title'),
        ];
    }

    public function creating(object $model, OriginalContext $context): ?object
    {
        $model->seed = bin2hex(random_bytes(32));

        if (! $model->slug) {
            $model->slug = $this->uniqueSlug($model->title);
        }

        return $model;
    }

    public function saving(object $model, OriginalContext $context): ?object
    {
        if ($model->starts_at && $model->ends_at && $model->ends_at->lte($model->starts_at)) {
            throw new ValidationException(['endsAt' => $this->translator->trans('fof-scavenger-hunt.api.ends_before_start')]);
        }

        foreach (['image' => 'image', 'completed_image' => 'completedImage'] as $column => $field) {
            if (! ImageReference::isValid($model->$column)) {
                throw new ValidationException([$field => $this->translator->trans('fof-scavenger-hunt.api.image_invalid')]);
            }
        }

        return $model;
    }

    public function created(object $model, OriginalContext $context): ?object
    {
        $this->events->dispatch(new Event\HuntCreated($model, $context->getActor()));

        return $model;
    }

    public function updated(object $model, OriginalContext $context): ?object
    {
        $this->events->dispatch(new Event\HuntUpdated($model, $context->getActor()));

        return $model;
    }

    public function deleted(object $model, OriginalContext $context): void
    {
        $this->events->dispatch(new Event\HuntDeleted($model, $context->getActor()));
    }

    protected function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 90, '') ?: 'hunt';
        $slug = $base;

        for ($i = 2; Hunt::query()->where('slug', $slug)->exists(); $i++) {
            $slug = "$base-$i";
        }

        return $slug;
    }
}
