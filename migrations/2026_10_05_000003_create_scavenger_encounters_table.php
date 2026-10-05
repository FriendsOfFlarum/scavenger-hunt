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

return Migration::createTable('fof_scavenger_encounters', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('part_id');
    $table->unsignedInteger('user_id');
    $table->unsignedInteger('location_id');
    $table->char('token_hash', 64)->unique();
    $table->dateTime('created_at');
    // Unix milliseconds: claim_ms needs a precision a portable datetime column does not keep.
    $table->unsignedBigInteger('issued_at_ms');
    $table->dateTime('expires_at');
    $table->dateTime('claimed_at')->nullable();
    $table->unsignedInteger('claim_ms')->nullable();

    $table->index(['user_id', 'part_id', 'expires_at']);
    $table->index(['part_id', 'claimed_at']);
    $table->foreign('part_id')->references('id')->on('fof_scavenger_parts')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->foreign('location_id')->references('id')->on('fof_scavenger_locations')->cascadeOnDelete();
});
