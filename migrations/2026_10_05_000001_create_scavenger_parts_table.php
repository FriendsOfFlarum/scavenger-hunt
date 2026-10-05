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

return Migration::createTable('fof_scavenger_parts', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('hunt_id');
    $table->string('name', 100);
    $table->string('hint', 255)->nullable();
    $table->string('image')->nullable();
    $table->json('layer')->nullable();
    $table->unsignedSmallInteger('sort')->default(0);
    $table->string('mode', 20)->default('fixed');
    $table->unsignedTinyInteger('wander_chance')->nullable();
    $table->timestamps();

    $table->index(['hunt_id', 'sort']);
    $table->foreign('hunt_id')->references('id')->on('fof_scavenger_hunts')->cascadeOnDelete();
});
