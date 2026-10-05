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

return Migration::createTable('fof_scavenger_hunts', function (Blueprint $table) {
    $table->increments('id');
    $table->string('slug', 100)->unique();
    $table->string('title', 200);
    $table->text('description')->nullable();
    $table->string('template', 50)->nullable();
    $table->string('image')->nullable();
    $table->string('completed_image')->nullable();
    $table->dateTime('starts_at')->nullable();
    $table->dateTime('ends_at')->nullable();
    $table->boolean('is_enabled')->default(false);
    $table->boolean('guests_can_see')->default(true);
    $table->unsignedInteger('reward_group_id')->nullable();
    $table->mediumText('reward_message')->nullable();
    $table->char('seed', 64);
    $table->timestamps();

    $table->index(['is_enabled', 'starts_at', 'ends_at']);
    $table->foreign('reward_group_id')->references('id')->on('groups')->nullOnDelete();
});
