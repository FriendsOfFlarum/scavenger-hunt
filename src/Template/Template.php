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

final class Template
{
    /**
     * @param list<TemplatePart> $parts
     */
    public function __construct(
        public readonly string $key,
        public readonly string $occasion,
        public readonly ?string $image,
        public readonly ?string $completedImage,
        public readonly array $parts,
    ) {
    }

    public function reference(?string $file): ?string
    {
        return $file === null ? null : "template:$this->key/$file";
    }
}
