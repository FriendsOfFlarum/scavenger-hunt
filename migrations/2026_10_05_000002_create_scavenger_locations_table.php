<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('fof_scavenger_locations', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('part_id');
    $table->string('route', 100)->nullable();
    $table->string('spot', 100);
    $table->string('context_type', 30)->nullable();
    $table->unsignedInteger('context_id')->nullable();
    $table->timestamps();

    $table->index('part_id');
    $table->foreign('part_id')->references('id')->on('fof_scavenger_parts')->cascadeOnDelete();
});
