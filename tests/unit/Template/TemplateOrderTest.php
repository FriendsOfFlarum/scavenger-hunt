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

use Flarum\Locale\TranslatorInterface;
use FoF\ScavengerHunt\Template\TemplateRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TemplateOrderTest extends TestCase
{
    protected string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/fof-scavenger-hunt-templates-'.bin2hex(random_bytes(4));

        foreach (['zoo', 'christmas', 'birthday', 'spring'] as $key) {
            mkdir("$this->path/$key", 0777, true);
            file_put_contents("$this->path/$key/template.json", json_encode(['occasion' => $key, 'parts' => []]));
        }
    }

    protected function tearDown(): void
    {
        foreach (glob("$this->path/*/template.json") ?: [] as $file) {
            unlink($file);
            rmdir(dirname($file));
        }

        rmdir($this->path);
    }

    #[Test]
    public function templates_missing_from_the_order_follow_it_alphabetically(): void
    {
        $repository = new TemplateRepository($this->createStub(TranslatorInterface::class), $this->path);

        $this->assertSame(['spring', 'christmas', 'birthday', 'zoo'], array_keys($repository->all()));
    }
}
