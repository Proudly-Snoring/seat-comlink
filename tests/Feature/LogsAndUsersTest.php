<?php

namespace ProudlySnoring\Seat\Comlink\Tests\Feature;

use ProudlySnoring\Seat\Comlink\Models\ComlinkLog;
use ProudlySnoring\Seat\Comlink\Models\DiscordMember;
use ProudlySnoring\Seat\Comlink\Models\DiscordServer;
use ProudlySnoring\Seat\Comlink\Models\DiscordUser;
use ProudlySnoring\Seat\Comlink\Services\ComlinkLogger;
use ProudlySnoring\Seat\Comlink\Tests\TestCase;
use Seat\Web\Models\User;

class LogsAndUsersTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin', null, true);
    }

    public function testLogsAreSortedAndFiltered()
    {
        ComlinkLogger::info('first info');
        ComlinkLogger::error('an error');
        ComlinkLogger::warning('other driver', [], 'mumble');
        ComlinkLogger::debug('some debug');
        ComlinkLog::where('message', 'first info')->update(['created_at' => '2026-01-10 10:00:00']);
        ComlinkLog::where('message', 'an error')->update(['created_at' => '2026-02-10 10:00:00']);
        ComlinkLog::where('message', 'other driver')->update(['created_at' => '2026-03-10 10:00:00']);

        $this->actingAs($this->admin)->get(route('seatcomlink::logs'))
            ->assertOk()
            ->assertSee('<span class="input-group-text" id="log_level">' . trans('comlink::comlink.level') . '</span>', false)
            ->assertSee('<label class="input-group-text" for="log_driver">' . trans('comlink::comlink.driver') . '</label>', false)
            ->assertSee('<label class="input-group-text" for="log_user">' . trans('comlink::comlink.log_user') . '</label>', false)
            ->assertSee('<label class="input-group-text" for="log_from">' . trans('comlink::comlink.from') . '</label>', false)
            ->assertSee('<label class="input-group-text" for="log_to">' . trans('comlink::comlink.to') . '</label>', false)
            ->assertSee('<label class="btn btn-outline-danger active">', false)
            ->assertSee('<label class="btn btn-outline-secondary">', false)
            ->assertSee('value="error" id="log_level_error" autocomplete="off" checked', false)
            ->assertDontSee('value="debug" id="log_level_debug" autocomplete="off" checked', false)
            ->assertSeeInOrder(['other driver', 'an error', 'first info'])
            ->assertDontSee('some debug');

        $this->actingAs($this->admin)->get(route('seatcomlink::logs', ['level' => ['error']]))
            ->assertSee('an error')->assertDontSee('first info')->assertDontSee('other driver')
            ->assertSee('value="error" id="log_level_error" autocomplete="off" checked', false)
            ->assertDontSee('value="info" id="log_level_info" autocomplete="off" checked', false);

        $this->actingAs($this->admin)->get(route('seatcomlink::logs', ['level' => ['debug']]))
            ->assertSee('some debug')->assertDontSee('an error');

        $this->actingAs($this->admin)->get(route('seatcomlink::logs', ['level' => ['info', 'error']]))
            ->assertSee('an error')->assertSee('first info')->assertDontSee('other driver');

        $this->actingAs($this->admin)->get(route('seatcomlink::logs', ['driver' => 'mumble']))
            ->assertSee('other driver')->assertDontSee('an error');

        $this->actingAs($this->admin)->get(route('seatcomlink::logs', ['from' => '2026-02-01', 'to' => '2026-02-28']))
            ->assertSee('an error')->assertDontSee('first info')->assertDontSee('other driver');

        $this->actingAs($this->admin)->get(route('seatcomlink::logs', ['level' => ['nope']]))->assertSessionHasErrors('level.0');
        $this->actingAs($this->admin)->get(route('seatcomlink::logs', ['level' => 'error']))->assertSessionHasErrors('level');
    }

    public function testLogsUserColumnAndFilter()
    {
        $alice = $this->makeUser('Alice Pilot');
        $this->actingAs($alice);
        ComlinkLogger::info('by alice');
        ComlinkLogger::debug('debug by alice');
        auth()->logout();
        ComlinkLogger::info('by the sync');

        $this->actingAs($this->admin)->get(route('seatcomlink::logs'))
            ->assertSee('<th>' . trans('comlink::comlink.log_user') . '</th>', false)
            ->assertSee('<input type="text" name="user" id="log_user" class="form-control" value=""', false)
            ->assertSeeInOrder(['Alice Pilot', '<td>by alice</td>'], false)
            ->assertSeeInOrder([trans('comlink::comlink.log_system'), '<td>by the sync</td>'], false);

        $this->actingAs($this->admin)->get(route('seatcomlink::logs', ['user' => ' ali ']))
            ->assertSee('value="ali"', false)
            ->assertSee('<td>by alice</td>', false)->assertDontSee('by the sync');
        $this->actingAs($this->admin)->get(route('seatcomlink::logs', ['user' => 'SYS']))
            ->assertSee('by the sync')->assertDontSee('by alice');
        $this->actingAs($this->admin)->get(route('seatcomlink::logs', ['user' => 'nobody']))
            ->assertSee(trans('comlink::comlink.no_logs'));

        // combined with another filter: the "System" alternative must not leak
        $this->actingAs($this->admin)->get(route('seatcomlink::logs', ['user' => 'e', 'level' => ['debug']]))
            ->assertSee('debug by alice')->assertDontSee('<td>by alice</td>', false)->assertDontSee('by the sync');
    }

    public function testLogsArePaginated()
    {
        foreach (range(1, 60) as $i)
            ComlinkLogger::info(sprintf('log #%02d', $i));

        $this->actingAs($this->admin)->get(route('seatcomlink::logs'))->assertSee('log #60')->assertDontSee('log #10');
        $this->actingAs($this->admin)->get(route('seatcomlink::logs', ['page' => 2]))->assertSee('log #10')->assertDontSee('log #60');
    }

    public function testUsersView()
    {
        $user = $this->makeUser('alice-account', ['id' => 1001, 'name' => 'Alice Pilot']);
        DiscordUser::create(['user_id' => $user->id, 'discord_id' => '11', 'username' => 'alice_d']);
        DiscordServer::create(['server_id' => '100', 'name' => 'Alliance']);
        DiscordServer::create(['server_id' => '200', 'name' => 'Corp']);
        DiscordMember::create(['server_id' => '100', 'discord_id' => '11', 'nickname' => '[ALLY] Alice']);
        DiscordMember::create(['server_id' => '200', 'discord_id' => '11', 'nickname' => '[CORP] Alice']);

        $other = $this->makeUser('Bob Pilot');
        DiscordUser::create(['user_id' => $other->id, 'discord_id' => '12', 'username' => 'bob_d']);

        $this->actingAs($this->admin)->get(route('seatcomlink::users'))
            ->assertOk()
            ->assertSee('Alice Pilot')
            ->assertSee('alice_d')
            ->assertSee('<b>Alliance</b>:', false)
            ->assertSee('[ALLY] Alice')
            ->assertSee('[CORP] Alice')
            ->assertSee('bob_d')
            ->assertSee(trans('comlink::comlink.no_server'));

        $this->actingAs($this->admin)->get(route('seatcomlink::users', ['search' => 'bob']))
            ->assertSee('bob_d')->assertDontSee('alice_d');
        $this->actingAs($this->admin)->get(route('seatcomlink::users', ['search' => '11']))
            ->assertSee('alice_d')->assertDontSee('bob_d');
    }
}
