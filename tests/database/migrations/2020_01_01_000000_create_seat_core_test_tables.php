<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal subset of the SeAT core tables used by the plugin (tests only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->unique();
            $table->boolean('active')->default(true);
            $table->boolean('admin')->default(false);
            $table->rememberToken();
            $table->bigInteger('main_character_id')->nullable();
            $table->timestamps();
        });

        Schema::create('user_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('name');
            $table->text('value');
            $table->timestamps();
        });

        Schema::create('global_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->mediumText('value');
            $table->timestamps();
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('command');
            $table->string('expression');
            $table->boolean('allow_overlap')->default(false);
            $table->boolean('allow_maintenance')->default(false);
            $table->string('ping_before')->nullable();
            $table->string('ping_after')->nullable();
            $table->timestamps();
        });

        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->bigInteger('character_id')->primary();
            $table->unsignedSmallInteger('version')->default(2);
            $table->bigInteger('user_id');
            $table->mediumText('refresh_token')->nullable();
            $table->longText('scopes')->nullable();
            $table->dateTime('expires_on')->nullable();
            $table->text('token')->nullable();
            $table->string('character_owner_hash')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('character_infos', function (Blueprint $table) {
            $table->bigInteger('character_id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('character_affiliations', function (Blueprint $table) {
            $table->bigInteger('character_id')->primary();
            $table->bigInteger('corporation_id');
            $table->bigInteger('alliance_id')->nullable();
            $table->bigInteger('faction_id')->nullable();
            $table->timestamps();
        });

        Schema::create('corporation_infos', function (Blueprint $table) {
            $table->bigInteger('corporation_id')->primary();
            $table->string('name');
            $table->string('ticker');
            $table->bigInteger('alliance_id')->nullable();
            $table->timestamps();
        });

        Schema::create('alliances', function (Blueprint $table) {
            $table->bigInteger('alliance_id')->primary();
            $table->string('name');
            $table->string('ticker');
            $table->timestamps();
        });

        Schema::create('squads', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('type', ['hidden', 'manual', 'auto']);
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->mediumText('logo')->nullable();
            $table->json('filters')->nullable();
            $table->boolean('is_classified')->default(false);
            $table->timestamps();
        });

        Schema::create('squad_member', function (Blueprint $table) {
            $table->unsignedInteger('squad_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();
            $table->primary(['squad_id', 'user_id']);
        });

        Schema::create('squad_moderator', function (Blueprint $table) {
            $table->unsignedInteger('squad_id');
            $table->unsignedInteger('user_id');
            $table->primary(['squad_id', 'user_id']);
        });

        Schema::create('squad_applications', function (Blueprint $table) {
            $table->increments('application_id');
            $table->unsignedInteger('squad_id');
            $table->unsignedInteger('user_id');
            $table->text('message')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('user_id');
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->unsignedInteger('permission_id');
            $table->unsignedInteger('role_id');
            $table->boolean('not')->default(false);
            $table->json('filters')->nullable();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('security_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->text('message');
            $table->string('category')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('user_login_histories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('source')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('action')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'user_login_histories', 'security_logs', 'permission_role', 'permissions', 'role_user', 'roles', 'squad_applications',
            'squad_moderator', 'squad_member', 'squads', 'alliances', 'corporation_infos', 'character_affiliations',
            'character_infos', 'refresh_tokens', 'schedules', 'global_settings', 'user_settings', 'users',
        ] as $table)
            Schema::dropIfExists($table);
    }
};
