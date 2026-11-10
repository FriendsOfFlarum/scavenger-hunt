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

return Migration::createTable('fof_scavenger_assets', function (Blueprint $table) {
    $table->increments('id');
    $table->string('path');
    $table->string('name', 100);
    $table->string('mime_type', 50);
    $table->unsignedSmallInteger('width')->nullable();
    $table->unsignedSmallInteger('height')->nullable();
    $table->unsignedInteger('size');
    $table->unsignedInteger('user_id')->nullable();
    $table->dateTime('created_at');

    $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
});
