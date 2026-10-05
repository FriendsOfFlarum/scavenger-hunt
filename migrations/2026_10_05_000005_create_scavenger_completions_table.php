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

return Migration::createTable('fof_scavenger_completions', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('hunt_id');
    $table->unsignedInteger('user_id');
    $table->dateTime('completed_at');

    $table->unique(['hunt_id', 'user_id']);
    $table->index(['hunt_id', 'completed_at']);
    $table->index('user_id');
    $table->foreign('hunt_id')->references('id')->on('fof_scavenger_hunts')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
});
