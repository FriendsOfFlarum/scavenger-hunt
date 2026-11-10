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

use DOMDocument;
use DOMElement;
use enshrined\svgSanitize\Sanitizer;

/**
 * Uploaded SVGs are served from the forum's own origin, so everything that could run or call
 * out is stripped: scripts, event-handler attributes and references to other hosts.
 */
class SvgSanitizer
{
    /**
     * The sanitized document, or null if it isn't an SVG the sanitizer can parse.
     */
    public function sanitize(string $svg): ?string
    {
        $sanitizer = new Sanitizer();
        $sanitizer->removeRemoteReferences(true);

        $clean = $sanitizer->sanitize($svg);

        if (! is_string($clean) || $this->root($clean)?->localName !== 'svg') {
            return null;
        }

        return $clean;
    }

    /**
     * Width and height from the root's own attributes, or failing that its viewBox. Relative
     * units such as percentages say nothing about the image itself, so they don't count.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public function dimensions(string $svg): array
    {
        $root = $this->root($svg);

        if (! $root) {
            return [null, null];
        }

        $width = $this->length($root->getAttribute('width'));
        $height = $this->length($root->getAttribute('height'));

        if ($width === null || $height === null) {
            $viewBox = preg_split('/[\s,]+/', trim($root->getAttribute('viewBox'))) ?: [];

            if (count($viewBox) !== 4 || ! is_numeric($viewBox[2]) || ! is_numeric($viewBox[3])) {
                return [null, null];
            }

            [$width, $height] = [(float) $viewBox[2], (float) $viewBox[3]];
        }

        $width = (int) round($width);
        $height = (int) round($height);

        // The columns are unsigned smallints.
        if ($width < 1 || $height < 1 || $width > 65535 || $height > 65535) {
            return [null, null];
        }

        return [$width, $height];
    }

    protected function length(string $value): ?float
    {
        return preg_match('/^\s*(\d*\.?\d+)\s*(px)?\s*$/', $value, $matches) === 1 ? (float) $matches[1] : null;
    }

    protected function root(string $svg): ?DOMElement
    {
        if (trim($svg) === '') {
            return null;
        }

        $document = new DOMDocument();
        $internalErrors = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($svg, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($internalErrors);
        }

        return $loaded ? $document->documentElement : null;
    }
}
