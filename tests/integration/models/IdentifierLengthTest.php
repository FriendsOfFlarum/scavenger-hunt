<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\models;

use Flarum\Database\DatabaseRequirements;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class IdentifierLengthTest extends TestCase
{
    protected const TABLES = ['hunts', 'parts', 'locations', 'encounters', 'finds', 'completions', 'assets'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-scavenger-hunt');
    }

    /**
     * Core accepts a table prefix as long as its own longest identifier leaves room for, and
     * Laravel puts the prefix in front of generated index and key names.
     */
    #[Test]
    public function index_and_key_names_leave_room_for_any_table_prefix_core_accepts(): void
    {
        $this->app();

        $schema = $this->database()->getSchemaBuilder();
        $prefix = $this->database()->getTablePrefix();
        $tooLong = [];

        foreach (self::TABLES as $table) {
            $names = array_merge(
                array_column($schema->getIndexes("fof_scavenger_$table"), 'name'),
                array_column($schema->getForeignKeys("fof_scavenger_$table"), 'name'),
            );

            foreach (array_filter($names) as $name) {
                $unprefixed = $prefix !== '' && str_starts_with($name, $prefix) ? substr($name, strlen($prefix)) : $name;

                if (strlen($unprefixed) > DatabaseRequirements::LONGEST_MIGRATION_IDENTIFIER) {
                    $tooLong[] = $unprefixed;
                }
            }
        }

        $this->assertSame([], $tooLong);
    }
}
