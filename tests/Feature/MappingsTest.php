<?php

namespace ProudlySnoring\Seat\Comlink\Tests\Feature;

use ProudlySnoring\Seat\Comlink\Models\ComlinkLog;
use ProudlySnoring\Seat\Comlink\Models\DiscordIssue;
use ProudlySnoring\Seat\Comlink\Models\DiscordMapping;
use ProudlySnoring\Seat\Comlink\Models\DiscordMember;
use ProudlySnoring\Seat\Comlink\Models\DiscordRole;
use ProudlySnoring\Seat\Comlink\Models\DiscordServer;
use ProudlySnoring\Seat\Comlink\Services\ComlinkSettings;
use ProudlySnoring\Seat\Comlink\Tests\FakesDiscord;
use ProudlySnoring\Seat\Comlink\Tests\TestCase;
use Seat\Web\Models\Squads\Squad;
use Seat\Web\Models\User;

class MappingsTest extends TestCase
{
    use FakesDiscord;

    private User $admin;

    private Squad $squad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin', null, true);
        $this->squad = $this->makeSquad('Pilots');

        DiscordServer::create(['server_id' => '100', 'name' => 'Alliance', 'bot_top_position' => 5]);
        DiscordRole::create(['server_id' => '100', 'role_id' => '100', 'name' => '@everyone', 'position' => 0]);
        DiscordRole::create(['server_id' => '100', 'role_id' => '101', 'name' => 'Pilot', 'position' => 2]);
        DiscordRole::create(['server_id' => '100', 'role_id' => '102', 'name' => 'Director', 'position' => 8]);
        DiscordRole::create(['server_id' => '100', 'role_id' => '103', 'name' => 'Nitro', 'position' => 1, 'managed' => true]);
    }

    private function mapping(array $overrides = []): array
    {
        return array_merge(['server_id' => '100', 'squad_id' => $this->squad->id, 'role_id' => '101'], $overrides);
    }

    public function testUnmanageableRolesAreDisabled()
    {
        $this->actingAs($this->admin)->get(route('seatcomlink::mappings'))
            ->assertOk()
            ->assertSee('<option value="101" data-server="100" >', false)
            ->assertSee('<option value="102" data-server="100" disabled>', false)
            ->assertSee(trans('comlink::comlink.role_reason_hierarchy'))
            ->assertSee(trans('comlink::comlink.role_reason_integration'))
            ->assertSee(trans('comlink::comlink.role_reason_everyone'));
    }

    public function testAddMapping()
    {
        $this->actingAs($this->admin)->post(route('seatcomlink::mappings.store'), $this->mapping())
            ->assertSessionHas('success');

        $this->assertSame(1, DiscordMapping::count());
        $this->assertSame('info', ComlinkLog::first()->level);
        $this->assertSame('Added mapping Pilots -> Pilot for discord server Alliance', ComlinkLog::first()->message);

        $this->actingAs($this->admin)->get(route('seatcomlink::mappings'))->assertSee('Pilots')->assertSee('Pilot');
    }

    public function testInvalidMappingsAreRejected()
    {
        $cases = [
            ['role_id' => '102'],   // above the bot
            ['role_id' => '103'],   // integration
            ['role_id' => '100'],   // @everyone
            ['role_id' => '999'],   // unknown
            ['server_id' => '999'], // unknown server
            ['squad_id' => 999],    // unknown squad
        ];

        foreach ($cases as $case) {
            $this->actingAs($this->admin)->post(route('seatcomlink::mappings.store'), $this->mapping($case))
                ->assertSessionHasErrors();
        }

        // duplicates
        DiscordMapping::create($this->mapping());
        $this->actingAs($this->admin)->post(route('seatcomlink::mappings.store'), $this->mapping())
            ->assertSessionHasErrors('role_id');

        $this->assertSame(1, DiscordMapping::count());
        $this->assertSame(0, ComlinkLog::count());
    }

    public function testEditAndDeleteMapping()
    {
        $other = $this->makeSquad('Others');
        $mapping = DiscordMapping::create($this->mapping());

        // saving the same mapping is not a duplicate
        $this->actingAs($this->admin)->put(route('seatcomlink::mappings.update', $mapping), $this->mapping())
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->put(route('seatcomlink::mappings.update', $mapping), $this->mapping(['squad_id' => $other->id]))
            ->assertSessionHas('success');
        $this->assertSame($other->id, (int) $mapping->fresh()->squad_id);
        $this->assertSame(1, ComlinkLog::where('message', 'Edited mapping Others -> Pilot for discord server Alliance')->count());

        $this->actingAs($this->admin)->delete(route('seatcomlink::mappings.destroy', $mapping))->assertSessionHas('success');
        $this->assertSame(0, DiscordMapping::count());
        $this->assertSame(1, ComlinkLog::where('message', 'Removed mapping Others -> Pilot for discord server Alliance')->count());
    }

    public function testFilters()
    {
        $other = $this->makeSquad('Others');
        DiscordServer::create(['server_id' => '200', 'name' => 'Corp', 'bot_top_position' => 5]);
        DiscordRole::create(['server_id' => '200', 'role_id' => '201', 'name' => 'Member', 'position' => 1]);
        DiscordMapping::create($this->mapping());
        DiscordMapping::create(['server_id' => '200', 'squad_id' => $other->id, 'role_id' => '201']);

        $this->actingAs($this->admin)->get(route('seatcomlink::mappings', ['server' => '200']))
            ->assertSee('<td>Corp</td>', false)->assertDontSee('<td>Alliance</td>', false);

        $this->actingAs($this->admin)->get(route('seatcomlink::mappings', ['squad' => $this->squad->id]))
            ->assertSee('<td>Pilots <span', false)->assertDontSee('<td>Others <span', false);

        $this->actingAs($this->admin)->get(route('seatcomlink::mappings', ['role' => 'memb']))
            ->assertSee('<td>Others <span', false)->assertDontSee('<td>Pilots <span', false);
    }

    public function testSquadAndRoleCounts()
    {
        $squad = $this->makeSquad('Pilots squad', [$this->admin, $this->makeUser('pilot')]);
        DiscordMapping::create($this->mapping(['squad_id' => $squad->id]));
        DiscordMapping::create($this->mapping(['role_id' => '103']));

        // role 101 seen on 7 members at the last sync, 103 never synced
        DiscordRole::where('role_id', '101')->update(['member_count' => 7]);

        $this->actingAs($this->admin)->get(route('seatcomlink::mappings'))
            ->assertSee('Pilots squad <span class="text-muted" title="' . trans('comlink::comlink.squad_count') . '">(2)</span>', false)
            ->assertSee('<td>Pilots <span class="text-muted" title="' . trans('comlink::comlink.squad_count') . '">(0)</span>', false)
            ->assertSee('title="' . e(trans('comlink::comlink.role_count')) . '">(7)</span>', false)
            ->assertSee(trans('comlink::comlink.role_count'), false);
    }

    public function testButtonsInFooter()
    {
        $this->actingAs($this->admin)->get(route('seatcomlink::mappings'))
            ->assertSeeInOrder(['card-footer', route('seatcomlink::mappings.import'), route('seatcomlink::mappings.sync'), 'comlink-add-mapping'], false);
    }

    public function testOrphanBadge()
    {
        DiscordMapping::create($this->mapping());
        DiscordRole::where('role_id', '101')->first()->delete();

        $this->actingAs($this->admin)->get(route('seatcomlink::mappings'))
            ->assertSee(trans('comlink::comlink.orphan_help'));
    }

    public function testImportRolesNow()
    {
        ComlinkSettings::setCredentials('1', 'secret', 'token');
        DiscordMapping::create($this->mapping(['role_id' => '101']));

        // role 101 (mapped) deleted on discord, new role 104, a new server
        $this->addFakeGuild('100', 'Alliance renamed', ['104' => ['Fleet', 3], '110' => ['Bot', 6]], [], '1', ['110']);
        $this->addFakeGuild('300', 'New server');
        $this->installDiscordFake();

        $this->actingAs($this->admin)->post(route('seatcomlink::mappings.import'))->assertSessionHas('success');

        $this->assertSame('Alliance renamed', DiscordServer::where('server_id', '100')->value('name'));
        $this->assertSame(6, DiscordServer::where('server_id', '100')->value('bot_top_position'));
        $this->assertTrue(DiscordRole::withTrashed()->where('role_id', '101')->first()->trashed());
        $this->assertSame('Fleet', DiscordRole::where('role_id', '104')->value('name'));
        $this->assertTrue(DiscordServer::where('server_id', '300')->first()->present);

        $this->assertSame(1, ComlinkLog::where('level', 'error')
            ->where('message', 'Role Pilot deleted from server Alliance renamed, its mappings are now orphan')->count());
        $this->assertSame(1, ComlinkLog::where('message', 'Invited to server New server')->count());

        $log = ComlinkLog::where('message', 'Imported discord roles from 2 servers')->sole();
        $this->assertSame('info', $log->level);
        $this->assertSame($this->admin->id, (int) $log->user_id);

        // no member was touched
        $this->assertSame([], $this->fake_writes);
    }

    public function testServerIconsAreDownloaded()
    {
        ComlinkSettings::setCredentials('1', 'secret', 'token');
        $this->addFakeGuild('100', 'Alliance');
        $this->addFakeGuild('300', 'No icon');
        $this->fake_icons['100'] = 'abc';
        $this->installDiscordFake();

        $this->actingAs($this->admin)->post(route('seatcomlink::mappings.import'))->assertSessionHas('success');

        $server = DiscordServer::where('server_id', '100')->sole();
        $this->assertSame('abc', $server->icon);
        $this->assertSame('data:image/png;base64,' . base64_encode('png:/icons/100/abc.png'), $server->icon_data);
        $this->assertNull(DiscordServer::where('server_id', '300')->value('icon_data'));
        $this->assertSame(1, $this->fake_icon_downloads);

        // unchanged: cached
        $this->actingAs($this->admin)->post(route('seatcomlink::mappings.import'));
        $this->assertSame(1, $this->fake_icon_downloads);

        // changed: downloaded again
        $this->fake_icons['100'] = 'def';
        $this->actingAs($this->admin)->post(route('seatcomlink::mappings.import'));
        $this->assertSame(2, $this->fake_icon_downloads);
        $this->assertSame('data:image/png;base64,' . base64_encode('png:/icons/100/def.png'), DiscordServer::where('server_id', '100')->value('icon_data'));

        // removed
        unset($this->fake_icons['100']);
        $this->actingAs($this->admin)->post(route('seatcomlink::mappings.import'));
        $server->refresh();
        $this->assertNull($server->icon);
        $this->assertNull($server->icon_data);
    }

    public function testServerIconFailureIsRetried()
    {
        ComlinkSettings::setCredentials('1', 'secret', 'token');
        $this->addFakeGuild('100', 'Alliance');
        $this->fake_icons['100'] = 'abc';
        $this->failFake('GET', '/icons/100/abc.png', 500);
        $this->installDiscordFake();

        $this->actingAs($this->admin)->post(route('seatcomlink::mappings.import'))->assertSessionHas('success');

        $this->assertNull(DiscordServer::where('server_id', '100')->value('icon_data'));
        $this->assertSame(1, ComlinkLog::where('level', 'warning')
            ->where('message', 'Unable to download the icon of server Alliance: HTTP 500')->count());

        // retried at each import until it succeeds
        unset($this->fake_failures['GET /icons/100/abc.png']);
        $this->actingAs($this->admin)->post(route('seatcomlink::mappings.import'));
        $this->assertNotNull(DiscordServer::where('server_id', '100')->value('icon_data'));
    }

    public function testImportRolesDetectsRemovedServers()
    {
        ComlinkSettings::setCredentials('1', 'secret', 'token');
        $this->installDiscordFake();

        $this->actingAs($this->admin)->post(route('seatcomlink::mappings.import'))->assertSessionHas('success');

        $this->assertFalse(DiscordServer::where('server_id', '100')->first()->present);
        $this->assertSame(1, ComlinkLog::where('message', 'Removed from server Alliance')->count());
    }

    public function testRemovedServerForgetsMembersAndKeepsMappings()
    {
        ComlinkSettings::setCredentials('1', 'secret', 'token');
        DiscordMapping::create($this->mapping());
        DiscordMember::create(['server_id' => '100', 'discord_id' => '11', 'nickname' => 'Alice']);
        DiscordIssue::create(['server_id' => '100', 'discord_id' => '11', 'kind' => 'nickname', 'reason_hash' => 'x']);
        $this->installDiscordFake();

        $this->actingAs($this->admin)->post(route('seatcomlink::mappings.import'))->assertSessionHas('success');

        $this->assertSame(0, DiscordMember::count());
        $this->assertSame(0, DiscordIssue::count());
        $this->assertSame(1, DiscordMapping::count());
    }

    public function testMappingsOfRemovedServersAreFlaggedAndNotEditable()
    {
        $mapping = DiscordMapping::create($this->mapping());

        $this->actingAs($this->admin)->get(route('seatcomlink::mappings'))
            ->assertSee('<td>Alliance</td>', false)
            ->assertDontSee(trans('comlink::comlink.absent_help'));

        DiscordServer::where('server_id', '100')->update(['present' => false]);

        $this->actingAs($this->admin)->get(route('seatcomlink::mappings'))
            ->assertSee(trans('comlink::comlink.absent_help'))
            ->assertSee('data-role="101"
                        disabled>', false);

        // edit rejected server side, delete allowed
        $this->actingAs($this->admin)->put(route('seatcomlink::mappings.update', $mapping), $this->mapping())
            ->assertSessionHasErrors('server_id');
        $this->actingAs($this->admin)->delete(route('seatcomlink::mappings.destroy', $mapping))->assertSessionHas('success');
        $this->assertSame(0, DiscordMapping::count());
    }

    public function testImportRolesRequiresCredentials()
    {
        $this->actingAs($this->admin)->post(route('seatcomlink::mappings.import'))->assertSessionHas('error');
    }
}
