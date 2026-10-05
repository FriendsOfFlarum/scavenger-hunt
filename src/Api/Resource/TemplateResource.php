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

use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractResource;
use Flarum\Api\Resource\Contracts\Listable;
use Flarum\Api\Schema;
use FoF\ScavengerHunt\Image\ImageReference;
use FoF\ScavengerHunt\Permission;
use FoF\ScavengerHunt\Template\Template;
use FoF\ScavengerHunt\Template\TemplatePart;
use FoF\ScavengerHunt\Template\TemplateRepository;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * Templates live on disk, not in the database, so this is a plain listable resource.
 */
class TemplateResource extends AbstractResource implements Listable
{
    public function __construct(
        protected TemplateRepository $templates,
        protected ImageReference $images,
    ) {
    }

    public function type(): string
    {
        return 'scavenger-hunt-templates';
    }

    public function getId(object $model, OriginalContext $context): string
    {
        /** @var Template $model */
        return $model->key;
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated()
                ->can(Permission::MANAGE),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('title')
                ->get(fn (Template $template) => $this->templates->title($template)),
            Schema\Str::make('occasion')
                ->get(fn (Template $template) => $this->templates->occasion($template)),
            Schema\Str::make('imageUrl')
                ->nullable()
                ->get(fn (Template $template) => $this->images->url($template->reference($template->image))),
            Schema\Arr::make('parts')
                ->get(fn (Template $template) => array_map(fn (TemplatePart $part) => [
                    'key' => $part->key,
                    'name' => $this->templates->partName($template, $part),
                    'imageUrl' => $this->images->url($template->reference($part->image)),
                    'layer' => $part->layer,
                ], $template->parts)),
        ];
    }

    public function query(OriginalContext $context): object
    {
        return (object) [];
    }

    public function results(object $query, OriginalContext $context): iterable
    {
        return array_values($this->templates->all());
    }

    public function filters(): array
    {
        return [];
    }

    public function sorts(): array
    {
        return [];
    }

    public function resolveSorts(): array
    {
        return [];
    }
}
