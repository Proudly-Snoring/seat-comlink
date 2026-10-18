<?php

namespace ProudlySnoring\Seat\Comlink\Tests\Feature;

use ProudlySnoring\Seat\Comlink\Models\ComlinkLog;
use ProudlySnoring\Seat\Comlink\Models\DiscordMapping;
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
            ->assertSee('<td>Pilots</td>', false)->assertDontSee('<td>Others</td>', false);

        $this->actingAs($this->admin)->get(route('seatcomlink::mappings', ['role' => 'memb']))
            ->assertSee('<td>Others</td>', false)->assertDontSee('<td>Pilots</td>', false);
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

        // no member was touched
        $this->assertSame([], $this->fake_writes);
    }

    public function testImportRolesDetectsRemovedServers()
    {
        ComlinkSettings::setCredentials('1', 'secret', 'token');
        $this->installDiscordFake();

        $this->actingAs($this->admin)->post(route('seatcomlink::mappings.import'))->assertSessionHas('success');

        $this->assertFalse(DiscordServer::where('server_id', '100')->first()->present);
        $this->assertSame(1, ComlinkLog::where('message', 'Removed from server Alliance')->count());
    }

    public function testImportRolesRequiresCredentials()
    {
        $this->actingAs($this->admin)->post(route('seatcomlink::mappings.import'))->assertSessionHas('error');
    }
}
