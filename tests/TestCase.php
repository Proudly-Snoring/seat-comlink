<?php

namespace ProudlySnoring\Seat\Comlink\Tests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Lunaweb\RedisMock\Providers\RedisMockServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use ProudlySnoring\Seat\Comlink\ComlinkServiceProvider;
use Seat\Web\Models\Squads\Squad;
use Seat\Web\Models\User;
use Seat\Web\WebServiceProvider;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('k', 32)));
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('database.redis.client', 'mock');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('auth.providers.users.model', User::class);
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            RedisMockServiceProvider::class,
            WebServiceProvider::class,
            ComlinkServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // SeAT caches ACL in redis (mocked, shared across tests): start clean
        Cache::store('redis')->flush();

        // stub the SeAT layout (the real one needs most of the core tables)
        View::prependNamespace('web', __DIR__ . '/views');
    }

    /**
     * @param  \Illuminate\Routing\Router  $router
     */
    protected function defineRoutes($router)
    {
        // testbench auth middleware redirects guests to a "login" named route
        $router->get('/login', fn () => 'login')->name('login');
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom([
            '--path' => [
                realpath(__DIR__ . '/database/migrations'),
                realpath(__DIR__ . '/../src/database/migrations'),
            ],
            '--realpath' => true,
        ]);
    }

    /**
     * Create a SeAT user with an optional main character and affiliation.
     */
    protected function makeUser(string $name, ?array $character = null, bool $admin = false): User
    {
        $user = new User();
        $user->forceFill([
            'name' => $name,
            'admin' => $admin,
            'active' => true,
            'main_character_id' => $character['id'] ?? null,
        ])->save();

        if ($character)
            $this->addCharacter($user, $character);

        return $user;
    }

    /**
     * @param  array  $character  id, name, corp => [id, name, ticker], alliance => [id, name, ticker]
     */
    protected function addCharacter(User $user, array $character, ?string $created_at = null): void
    {
        $created_at = $created_at ?? now()->toDateTimeString();

        DB::table('refresh_tokens')->insert([
            'character_id' => $character['id'],
            'user_id' => $user->id,
            'created_at' => $created_at,
            'updated_at' => $created_at,
        ]);

        DB::table('character_infos')->insert([
            'character_id' => $character['id'],
            'name' => $character['name'],
        ]);

        if (! isset($character['corp']))
            return;

        DB::table('character_affiliations')->insert([
            'character_id' => $character['id'],
            'corporation_id' => $character['corp'][0],
            'alliance_id' => $character['alliance'][0] ?? null,
        ]);

        DB::table('corporation_infos')->insertOrIgnore([
            'corporation_id' => $character['corp'][0],
            'name' => $character['corp'][1],
            'ticker' => $character['corp'][2],
            'alliance_id' => $character['alliance'][0] ?? null,
        ]);

        if (isset($character['alliance'])) {
            DB::table('alliances')->insertOrIgnore([
                'alliance_id' => $character['alliance'][0],
                'name' => $character['alliance'][1],
                'ticker' => $character['alliance'][2],
            ]);
        }
    }

    protected function makeSquad(string $name, array $members = []): Squad
    {
        $id = DB::table('squads')->insertGetId([
            'type' => 'manual',
            'name' => $name,
            'filters' => '{}',
        ]);

        foreach ($members as $user)
            DB::table('squad_member')->insert(['squad_id' => $id, 'user_id' => $user->id]);

        return Squad::withoutGlobalScopes()->find($id);
    }

    /**
     * Grant a plugin permission to a non-admin user through a SeAT role.
     */
    protected function grant(User $user, string $permission): void
    {
        $role_id = DB::table('roles')->insertGetId(['title' => $permission]);
        $permission_id = DB::table('permissions')->insertGetId(['title' => $permission]);
        DB::table('permission_role')->insert(['permission_id' => $permission_id, 'role_id' => $role_id]);
        DB::table('role_user')->insert(['role_id' => $role_id, 'user_id' => $user->id]);
    }
}
