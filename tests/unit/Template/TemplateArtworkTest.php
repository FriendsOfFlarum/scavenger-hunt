<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\unit\Template;

use DOMDocument;
use FoF\ScavengerHunt\Image\SvgSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Every Template draws on a 400×400 canvas; each Part's SVG viewBox is its own box on that
 * canvas, and its `layer` is the same box in percent. If they drift apart the Collectable
 * assembles crooked.
 */
class TemplateArtworkTest extends TestCase
{
    public static function templates(): iterable
    {
        foreach (glob(__DIR__.'/../../../assets/templates/*/template.json') as $file) {
            yield basename(dirname($file)) => [dirname($file)];
        }
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    protected function viewBox(string $file): array
    {
        $this->assertFileExists($file);
        $this->assertMatchesRegularExpression('/viewBox="([\d.\-]+) ([\d.\-]+) ([\d.]+) ([\d.]+)"/', (string) file_get_contents($file));
        preg_match('/viewBox="([\d.\-]+) ([\d.\-]+) ([\d.]+) ([\d.]+)"/', (string) file_get_contents($file), $m);

        return [(float) $m[1], (float) $m[2], (float) $m[3], (float) $m[4]];
    }

    #[Test]
    #[DataProvider('templates')]
    public function part_artwork_lines_up_with_its_layer(string $directory): void
    {
        $template = json_decode((string) file_get_contents("$directory/template.json"), true, 512, JSON_THROW_ON_ERROR);

        if ($template['image'] !== null) {
            $this->assertSame([0.0, 0.0, 400.0, 400.0], $this->viewBox("$directory/{$template['image']}"));
        }

        foreach ($template['parts'] as $part) {
            [$x, $y, $width] = $this->viewBox("$directory/{$part['image']}");

            $this->assertEqualsWithDelta($part['layer']['x'] * 4, $x, 0.01, "{$part['key']} x");
            $this->assertEqualsWithDelta($part['layer']['y'] * 4, $y, 0.01, "{$part['key']} y");
            $this->assertEqualsWithDelta($part['layer']['width'] * 4, $width, 0.01, "{$part['key']} width");
        }
    }
    #[Test]
    #[DataProvider('templates')]
    public function every_title_occasion_and_part_is_translated(string $directory): void
    {
        $template = json_decode((string) file_get_contents("$directory/template.json"), true, 512, JSON_THROW_ON_ERROR);
        $key = basename($directory);
        $locale = Yaml::parseFile(__DIR__.'/../../../resources/locale/en.yml')['fof-scavenger-hunt']['lib']['templates'][$key] ?? null;

        $this->assertIsArray($locale, "no translations for $key");
        $this->assertNotEmpty($locale['title'] ?? null);
        $this->assertNotEmpty($locale['occasion'] ?? null);

        foreach ($template['parts'] as $part) {
            $this->assertNotEmpty($locale['parts'][$part['key']] ?? null, "no name for part {$part['key']}");
        }
    }

    /**
     * Shipped artwork is held to the same rules as uploads: nothing the sanitizer would strip.
     */
    #[Test]
    #[DataProvider('templates')]
    public function the_sanitizer_leaves_every_svg_unchanged(string $directory): void
    {
        $sanitizer = new SvgSanitizer();

        foreach (glob("$directory/*.svg") ?: [] as $file) {
            $svg = (string) file_get_contents($file);
            $clean = $sanitizer->sanitize($svg);

            $this->assertNotNull($clean, basename($file));
            $this->assertSame($this->canonical($svg), $this->canonical($clean), basename($file));
        }
    }

    protected function canonical(string $svg): string
    {
        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->loadXML($svg);

        return (string) $document->documentElement?->C14N();
    }
}
