<?php

/*
 * This file is part of seat-comlink.
 *
 * Copyright (C) 2026 Proudly-Snoring
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // link between a SeAT user and a discord account (no FK on core tables)
        Schema::create('comlink_discord_users', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('discord_id', 32)->unique();
            $table->string('username');
            $table->timestamps();
        });

        Schema::create('comlink_discord_servers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('server_id', 32)->unique();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->string('owner_id', 32)->nullable();
            $table->boolean('enforce_nickname')->default(true);
            $table->boolean('enforce_roles')->default(true);
            $table->string('nickname_format')->default('[{corp_ticker}] {character}');
            $table->integer('bot_top_position')->default(0);
            $table->boolean('present')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        // roles cache, refreshed at each sync - deleted_at marks orphan roles
        Schema::create('comlink_discord_roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('server_id', 32)->index();
            $table->string('role_id', 32)->unique();
            $table->string('name');
            $table->integer('position')->default(0);
            $table->unsignedInteger('color')->default(0);
            $table->boolean('managed')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('comlink_discord_mappings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('server_id', 32);
            $table->unsignedBigInteger('squad_id')->index();
            $table->string('role_id', 32)->index();
            $table->timestamps();

            $table->unique(['server_id', 'squad_id', 'role_id']);
        });

        // linked members as seen at the last synchronization
        Schema::create('comlink_discord_members', function (Blueprint $table) {
            $table->increments('id');
            $table->string('server_id', 32);
            $table->string('discord_id', 32)->index();
            $table->string('nickname')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'discord_id']);
        });

        // pending enforcement failures, used to not repeat the same warning at each sync
        Schema::create('comlink_discord_issues', function (Blueprint $table) {
            $table->increments('id');
            $table->string('server_id', 32);
            $table->string('discord_id', 32);
            $table->string('kind', 16);
            $table->string('target', 32)->default('');
            $table->string('reason_hash', 64);
            $table->timestamps();

            $table->unique(['server_id', 'discord_id', 'kind', 'target']);
        });

        Schema::create('comlink_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('level', 16)->index();
            $table->string('driver', 32)->index();
            $table->text('message');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('comlink_logs');
        Schema::dropIfExists('comlink_discord_issues');
        Schema::dropIfExists('comlink_discord_members');
        Schema::dropIfExists('comlink_discord_mappings');
        Schema::dropIfExists('comlink_discord_roles');
        Schema::dropIfExists('comlink_discord_servers');
        Schema::dropIfExists('comlink_discord_users');
    }
};
