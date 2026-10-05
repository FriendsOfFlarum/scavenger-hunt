<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Template;

use Carbon\Carbon;
use Flarum\Locale\TranslatorInterface;
use FoF\ScavengerHunt\Hunt;
use FoF\ScavengerHunt\Part;
use FoF\ScavengerHunt\PlacementMode;

class TemplateRepository
{
    /**
     * @var array<string, Template>|null
     */
    protected ?array $templates = null;

    public function __construct(
        protected TranslatorInterface $translator,
        protected string $path = __DIR__.'/../../assets/templates',
    ) {
    }

    /**
     * @return array<string, Template>
     */
    public function all(): array
    {
        if ($this->templates !== null) {
            return $this->templates;
        }

        $templates = [];

        foreach (glob($this->path.'/*/template.json') ?: [] as $file) {
            $key = basename(dirname($file));
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

            $templates[$key] = new Template(
                key: $key,
                occasion: (string) $data['occasion'],
                image: $data['image'] ?? null,
                completedImage: $data['completedImage'] ?? null,
                parts: array_map(
                    fn (array $part) => new TemplatePart((string) $part['key'], (string) $part['image'], $part['layer']),
                    $data['parts']
                ),
            );
        }

        ksort($templates);

        return $this->templates = $templates;
    }

    public function get(string $key): ?Template
    {
        return $this->all()[$key] ?? null;
    }

    public function title(Template $template): string
    {
        return $this->translator->trans("fof-scavenger-hunt.lib.templates.$template->key.title");
    }

    public function occasion(Template $template): string
    {
        return $this->translator->trans("fof-scavenger-hunt.lib.templates.$template->key.occasion");
    }

    public function partName(Template $template, TemplatePart $part): string
    {
        return $this->translator->trans("fof-scavenger-hunt.lib.templates.$template->key.parts.$part->key");
    }

    /**
     * Part names are copied in the language of whoever creates the Hunt; after that they are
     * ordinary, editable Parts. One insert for the lot.
     */
    public function copyParts(Template $template, Hunt $hunt): void
    {
        $now = Carbon::now();

        Part::query()->insert(array_map(fn (int $index, TemplatePart $part) => [
            'hunt_id' => $hunt->id,
            'name' => $this->partName($template, $part),
            'image' => $template->reference($part->image),
            'layer' => json_encode($part->layer),
            'sort' => $index,
            'mode' => PlacementMode::Fixed->value,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_keys($template->parts), $template->parts));
    }
}
