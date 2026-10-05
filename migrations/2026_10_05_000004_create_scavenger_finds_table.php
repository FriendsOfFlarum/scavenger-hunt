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

return Migration::createTable('fof_scavenger_finds', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('part_id');
    $table->unsignedInteger('user_id');
    $table->dateTime('found_at');

    $table->unique(['part_id', 'user_id']);
    $table->index(['user_id', 'found_at']);
    $table->foreign('part_id')->references('id')->on('fof_scavenger_parts')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
});
