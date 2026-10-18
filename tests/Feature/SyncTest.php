<?php

namespace ProudlySnoring\Seat\Comlink\Tests\Feature;

use Illuminate\Support\Facades\Queue;
use ProudlySnoring\Seat\Comlink\Database\Seeders\ComlinkScheduleSeeder;
use ProudlySnoring\Seat\Comlink\Jobs\DiscoverServers;
use ProudlySnoring\Seat\Comlink\Jobs\SyncServer;
use ProudlySnoring\Seat\Comlink\Models\ComlinkLog;
use ProudlySnoring\Seat\Comlink\Models\DiscordIssue;
use ProudlySnoring\Seat\Comlink\Models\DiscordMapping;
use ProudlySnoring\Seat\Comlink\Models\DiscordMember;
use ProudlySnoring\Seat\Comlink\Models\DiscordServer;
use ProudlySnoring\Seat\Comlink\Models\DiscordUser;
use ProudlySnoring\Seat\Comlink\Services\ComlinkSettings;
use ProudlySnoring\Seat\Comlink\Tests\FakesDiscord;
use ProudlySnoring\Seat\Comlink\Tests\TestCase;
use Seat\Services\Models\Schedule;

class SyncTest extends TestCase
{
    use FakesDiscord;

    const ROLES = [
        '101' => ['Pilot', 2],
        '102' => ['FC', 3],
        '103' => ['Director', 8],
        '104' => ['Unmanaged', 1],
        '110' => ['Bot', 6],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        ComlinkSettings::setCredentials('1', 'secret', 'token');

        $corp = [2001, 'Pilots Inc', 'PILOT'];
        $alice = $this->link('alice', '11', ['id' => 1001, 'name' => 'Alice', 'corp' => $corp]);
        $bob = $this->link('bob', '12', ['id' => 1002, 'name' => 'Bob', 'corp' => $corp]);
        $this->link('carol', '13', ['id' => 1003, 'name' => 'Carol', 'corp' => $corp]);
        $dave = $this->link('dave', '16', ['id' => 1006, 'name' => 'Dave', 'corp' => $corp]);
        $erin = $this->link('erin', '17', ['id' => 1007, 'name' => 'Erin', 'corp' => $corp]);
        $this->link('owner', '1', ['id' => 1008, 'name' => 'Owner', 'corp' => $corp]);

        $pilots = $this->makeSquad('Pilots', [$alice, $bob]);
        $fcs = $this->makeSquad('FCs', [$bob]);
        $directors = $this->makeSquad('Directors', [$dave, $erin]);

        DiscordServer::create(['server_id' => '100', 'name' => 'Alliance']);
        DiscordMapping::create(['server_id' => '100', 'squad_id' => $pilots->id, 'role_id' => '101']);
        DiscordMapping::create(['server_id' => '100', 'squad_id' => $fcs->id, 'role_id' => '101']);
        DiscordMapping::create(['server_id' => '100', 'squad_id' => $fcs->id, 'role_id' => '102']);
        DiscordMapping::create(['server_id' => '100', 'squad_id' => $directors->id, 'role_id' => '103']);

        $this->addFakeGuild('100', 'Alliance', self::ROLES, [
            '1' => ['owner_d', 'Boss', []],
            '11' => ['alice_d', null, ['104']],
            '12' => ['bob_d', '[PILOT] Bob', ['101', '102']],
            '13' => ['carol_d', '[PILOT] Carol', ['101', '104']],
            '14' => ['stranger', null, ['101', '102']],
            '15' => ['other_bot', null, ['101'], true],
            '16' => ['dave_d', '[PILOT] Dave', []],
            '17' => ['erin_d', 'Erin', ['103']],
        ], '1', ['110']);
        $this->installDiscordFake();
    }

    private function link(string $name, string $discord_id, array $character)
    {
        $user = $this->makeUser($name, $character);
        DiscordUser::create(['user_id' => $user->id, 'discord_id' => $discord_id, 'username' => $name . '_d']);

        return $user;
    }

    private function sync(): void
    {
        $this->artisan('comlink:discord:sync', ['--now' => true])->assertSuccessful();
    }

    private function warnings(): array
    {
        return ComlinkLog::where('level', 'warning')->orderBy('id')->pluck('message')->all();
    }

    public function testFullSynchronization()
    {
        $this->sync();

        $this->assertEqualsCanonicalizing([
            'PUT /guilds/100/members/11/roles/101',         // alice: squad Pilots
            'PATCH /guilds/100/members/11 [PILOT] Alice',   // alice: nickname
            'DELETE /guilds/100/members/13/roles/101',      // carol: no squad anymore
            'DELETE /guilds/100/members/14/roles/101',      // unknown member
            'DELETE /guilds/100/members/14/roles/102',      // unknown member
        ], $this->fake_writes);

        // unmanaged roles are never touched, bots are ignored
        $this->assertSame(['104', '101'], $this->fakeMember('100', '11')['roles']);
        $this->assertSame(['104'], $this->fakeMember('100', '13')['roles']);
        $this->assertSame(['101'], $this->fakeMember('100', '15')['roles']);

        $this->assertEqualsCanonicalizing([
            'Failed to update role for user Dave (discord id: 16) on server Alliance: add role Director: ' . trans('comlink::comlink.role_reason_hierarchy'),
            'Failed to update nickname for user Erin (discord id: 17) on server Alliance: ' . trans('comlink::comlink.reason_above_bot'),
            'Failed to update nickname for user Owner (discord id: 1) on server Alliance: ' . trans('comlink::comlink.reason_owner'),
        ], $this->warnings());

        $this->assertSame(1, ComlinkLog::where('message', 'Added role Pilot to user Alice (discord id: 11) on server Alliance')->count());
        $this->assertSame(1, ComlinkLog::where('message', 'Removed role Pilot from user stranger (discord id: 14) on server Alliance')->count());
        $this->assertSame(1, ComlinkLog::where('message', 'Updated nickname of user Alice (discord id: 11) on server Alliance to [PILOT] Alice')->count());

        // sync logs have no origin user
        $this->assertSame(0, ComlinkLog::whereNotNull('user_id')->count());

        // linked members only, with their nickname
        $this->assertSame('[PILOT] Alice', DiscordMember::where('discord_id', '11')->value('nickname'));
        $this->assertSame(6, DiscordMember::count());
        $this->assertNotNull(DiscordServer::first()->last_synced_at);
        $this->assertSame('1', DiscordServer::first()->owner_id);
        $this->assertSame(6, DiscordServer::first()->bot_top_position);
    }

    public function testSecondSynchronizationIsIdempotentAndQuiet()
    {
        $this->sync();
        $writes = count($this->fake_writes);
        $logs = ComlinkLog::count();

        $this->sync();

        $this->assertCount($writes, $this->fake_writes);
        $this->assertSame($logs, ComlinkLog::count());
        $this->assertSame(3, DiscordIssue::count());
    }

    public function testWarningIsLoggedAgainAfterResolution()
    {
        $this->failFake('PUT', '/guilds/100/members/11/roles/101');

        $this->sync();
        $this->sync();
        $failure = 'Failed to update role for user Alice (discord id: 11) on server Alliance: add role Pilot: Missing Permissions (50013)';
        $this->assertSame(1, collect($this->warnings())->filter(fn ($m) => $m === $failure)->count());

        // fixed: role added, issue resolved
        $this->fake_failures = [];
        $this->sync();
        $this->assertContains('101', $this->fakeMember('100', '11')['roles']);
        $this->assertSame(0, DiscordIssue::where('discord_id', '11')->count());

        // the role is removed on discord and the problem comes back
        $this->fake_guilds['100']['members']['11']['roles'] = ['104'];
        $this->failFake('PUT', '/guilds/100/members/11/roles/101');
        $this->sync();
        $this->assertSame(2, collect($this->warnings())->filter(fn ($m) => $m === $failure)->count());
    }

    public function testEnforceFlags()
    {
        DiscordServer::first()->update(['enforce_roles' => false, 'enforce_nickname' => false]);

        $this->sync();

        $this->assertSame([], $this->fake_writes);
        $this->assertSame([], $this->warnings());
        $this->assertSame('[PILOT] Bob', DiscordMember::where('discord_id', '12')->value('nickname'));
    }

    public function testEnforceRolesOnly()
    {
        DiscordServer::first()->update(['enforce_nickname' => false]);

        $this->sync();

        $this->assertEmpty(array_filter($this->fake_writes, fn ($write) => str_starts_with($write, 'PATCH')));
        $this->assertContains('PUT /guilds/100/members/11/roles/101', $this->fake_writes);
    }

    public function testNicknameFormatIsAppliedAndNormalized()
    {
        DiscordServer::first()->update(['enforce_roles' => false, 'nickname_format' => '  {character}   of {corp_name} {alliance_ticker}']);

        $this->sync();

        $this->assertContains('PATCH /guilds/100/members/11 Alice of Pilots Inc -', $this->fake_writes);
    }

    public function testOrphanMappingsAreIgnored()
    {
        $this->sync();

        // FC role deleted on discord (stale on the member list)
        $this->fake_guilds['100']['roles'] = array_values(array_filter($this->fake_guilds['100']['roles'], fn ($role) => $role['id'] !== '102'));
        $this->fake_guilds['100']['members']['14']['roles'] = ['102'];
        $this->fake_writes = [];

        $this->sync();

        $this->assertSame([], $this->fake_writes);
        $this->assertSame(1, ComlinkLog::where('level', 'error')
            ->where('message', 'Role FC deleted from server Alliance, its mappings are now orphan')->count());

        // logged once
        $this->sync();
        $this->assertSame(1, ComlinkLog::where('level', 'error')->count());
    }

    public function testUnlinkedUserLosesRolesAtNextSync()
    {
        $this->sync();
        DiscordUser::where('discord_id', '12')->delete();
        $this->sync();

        $this->assertContains('DELETE /guilds/100/members/12/roles/101', $this->fake_writes);
        $this->assertContains('DELETE /guilds/100/members/12/roles/102', $this->fake_writes);
        $this->assertSame(0, DiscordMember::where('discord_id', '12')->count());
    }

    public function testMissingIntentIsReported()
    {
        $this->failFake('GET', '/guilds/100/members', 403, 50001, 'Missing Access');

        $this->sync();

        $error = ComlinkLog::where('level', 'error')->first();
        $this->assertStringStartsWith('Failed to synchronize server Alliance: Missing Access (50001)', $error->message);
        $this->assertNull(DiscordServer::first()->last_synced_at);
    }

    public function testUnexpectedErrorsAreLogged()
    {
        // malformed discord payload: member without user
        $this->fake_guilds['100']['members']['20'] = ['nick' => null, 'roles' => []];

        try {
            SyncServer::dispatchSync('100');
            $this->fail('exception expected');
        } catch (\Throwable $e) {
            // rethrown for the queue manager
        }

        $this->assertSame(1, ComlinkLog::where('level', 'error')->where('message', 'like', 'Failed to synchronize server Alliance: %')->count());
    }

    public function testSingleServerOption()
    {
        $this->artisan('comlink:discord:sync', ['--server' => '100', '--now' => true])->assertSuccessful();
        $this->assertNotEmpty($this->fake_writes);

        $this->artisan('comlink:discord:sync', ['--server' => '999'])->assertFailed();
    }

    public function testCommandQueuesJobs()
    {
        Queue::fake();

        $this->artisan('comlink:discord:sync')->assertSuccessful();
        Queue::assertPushed(DiscoverServers::class, fn ($job) => $job->now === false);

        $this->artisan('comlink:discord:sync', ['--server' => '100'])->assertSuccessful();
        Queue::assertPushed(SyncServer::class, fn ($job) => $job->server_id === '100');
    }

    public function testCommandWithoutTokenDoesNothing()
    {
        ComlinkSettings::setCredentials(null, null, null);

        $this->artisan('comlink:discord:sync', ['--now' => true])->assertSuccessful();

        $this->assertSame([], $this->fake_writes);
    }

    public function testSyncNowButton()
    {
        Queue::fake();
        $admin = $this->makeUser('admin', null, true);

        $this->actingAs($admin)->get(route('seatcomlink::mappings'))
            ->assertSee(route('seatcomlink::mappings.sync'));

        $this->actingAs($admin)->post(route('seatcomlink::mappings.sync'))
            ->assertSessionHas('success', trans('comlink::comlink.sync_queued'));

        Queue::assertPushed(DiscoverServers::class);

        ComlinkSettings::setCredentials(null, null, null);
        $this->actingAs($admin)->post(route('seatcomlink::mappings.sync'))->assertSessionHas('error');
        Queue::assertPushed(DiscoverServers::class, 1);
    }

    public function testScheduleSeeder()
    {
        $this->seed(ComlinkScheduleSeeder::class);

        $this->assertSame('*/5 * * * *', Schedule::where('command', 'comlink:discord:sync')->value('expression'));
        $this->assertSame(1, Schedule::where('command', 'comlink:logs:prune')->count());

        // the admin's rate survives a restart
        ComlinkSettings::setSyncRate(20);
        $this->seed(ComlinkScheduleSeeder::class);
        $this->assertSame('*/20 * * * *', Schedule::where('command', 'comlink:discord:sync')->value('expression'));
    }
}
