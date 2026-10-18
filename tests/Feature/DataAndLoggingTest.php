<?php

namespace ProudlySnoring\Seat\Comlink\Tests\Feature;

use Illuminate\Support\Facades\DB;
use ProudlySnoring\Seat\Comlink\Database\Seeders\ComlinkScheduleSeeder;
use ProudlySnoring\Seat\Comlink\Models\ComlinkLog;
use ProudlySnoring\Seat\Comlink\Models\DiscordMapping;
use ProudlySnoring\Seat\Comlink\Models\DiscordRole;
use ProudlySnoring\Seat\Comlink\Models\DiscordServer;
use ProudlySnoring\Seat\Comlink\Models\DiscordUser;
use ProudlySnoring\Seat\Comlink\Services\ComlinkLogger;
use ProudlySnoring\Seat\Comlink\Services\ComlinkSettings;
use ProudlySnoring\Seat\Comlink\Tests\TestCase;
use Seat\Services\Models\Schedule;

class DataAndLoggingTest extends TestCase
{
    public function testSecretsAreEncrypted()
    {
        ComlinkSettings::setCredentials('123456789012345678', 'client-secret', 'bot-token');

        $this->assertSame('123456789012345678', ComlinkSettings::clientId());
        $this->assertSame('client-secret', ComlinkSettings::clientSecret());
        $this->assertSame('bot-token', ComlinkSettings::botToken());
        $this->assertTrue(ComlinkSettings::hasCredentials());

        $stored = DB::table('global_settings')->pluck('value')->implode(' ');
        $this->assertStringNotContainsString('client-secret', $stored);
        $this->assertStringNotContainsString('bot-token', $stored);
    }

    public function testDefaults()
    {
        $this->assertFalse(ComlinkSettings::hasCredentials());
        $this->assertSame(5, ComlinkSettings::syncRate());
        $this->assertSame(30, ComlinkSettings::logRetention());
    }

    public function testSyncRateUpdatesSchedule()
    {
        ComlinkSettings::setSyncRate(15);
        $this->assertSame(15, ComlinkSettings::syncRate());
        $this->assertSame('*/15 * * * *', Schedule::where('command', 'comlink:discord:sync')->value('expression'));

        ComlinkSettings::setSyncRate(60);
        $this->assertSame('0 * * * *', Schedule::where('command', 'comlink:discord:sync')->value('expression'));
        $this->assertSame(1, Schedule::where('command', 'comlink:discord:sync')->count());
    }

    public function testSeederKeepsExistingSchedules()
    {
        Schedule::create(['command' => 'comlink:logs:prune', 'expression' => '0 1 * * *']);

        $this->seed(ComlinkScheduleSeeder::class);
        $this->seed(ComlinkScheduleSeeder::class);

        $this->assertSame(1, Schedule::where('command', 'comlink:logs:prune')->count());
        $this->assertSame('0 1 * * *', Schedule::where('command', 'comlink:logs:prune')->value('expression'));
    }

    public function testLoggerRecordsOriginUser()
    {
        ComlinkLogger::info('system event');
        $this->assertNull(ComlinkLog::first()->user_id);

        $user = $this->makeUser('admin-user');
        $this->actingAs($user);
        ComlinkLogger::warning('user event', ['foo' => 'bar']);

        $log = ComlinkLog::where('message', 'user event')->first();
        $this->assertSame($user->id, (int) $log->user_id);
        $this->assertSame('warning', $log->level);
        $this->assertSame('discord', $log->driver);
        $this->assertSame(['foo' => 'bar'], $log->context);
    }

    public function testPruneCommand()
    {
        ComlinkLogger::info('old');
        ComlinkLogger::info('recent');
        ComlinkLog::where('message', 'old')->update(['created_at' => now()->subDays(40)]);
        ComlinkLog::where('message', 'recent')->update(['created_at' => now()->subDays(10)]);

        $this->artisan('comlink:logs:prune')->assertSuccessful();
        $this->assertSame(['recent'], ComlinkLog::pluck('message')->all());

        $this->artisan('comlink:logs:prune', ['--days' => 5])->assertSuccessful();
        $this->assertSame(0, ComlinkLog::count());

        $this->artisan('comlink:logs:prune', ['--days' => 'abc'])->assertFailed();
    }

    public function testDeletingUserDeletesLink()
    {
        $user = $this->makeUser('leaver');
        DiscordUser::create(['user_id' => $user->id, 'discord_id' => '42', 'username' => 'leaver#0']);

        $user->delete();

        $this->assertSame(0, DiscordUser::count());
        $this->assertSame(1, ComlinkLog::where('message', 'Unlinked discord account 42 with user ' . $user->id)->count());
    }

    public function testDeletingSquadDeletesMappings()
    {
        $squad = $this->makeSquad('Pilots');
        $other = $this->makeSquad('Others');
        DiscordServer::create(['server_id' => '1', 'name' => 'Alliance']);
        DiscordRole::create(['server_id' => '1', 'role_id' => '10', 'name' => 'Pilot']);
        DiscordMapping::create(['server_id' => '1', 'squad_id' => $squad->id, 'role_id' => '10']);
        DiscordMapping::create(['server_id' => '1', 'squad_id' => $other->id, 'role_id' => '10']);

        $squad->delete();

        $this->assertSame([$other->id], DiscordMapping::pluck('squad_id')->map('intval')->all());
        $this->assertSame(1, ComlinkLog::where('message', 'Removed mapping Pilots -> Pilot for discord server Alliance')->count());
    }
}
