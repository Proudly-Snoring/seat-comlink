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
        ComlinkLog::where('message', 'first info')->update(['created_at' => '2026-01-10 10:00:00']);
        ComlinkLog::where('message', 'an error')->update(['created_at' => '2026-02-10 10:00:00']);
        ComlinkLog::where('message', 'other driver')->update(['created_at' => '2026-03-10 10:00:00']);

        $this->actingAs($this->admin)->get(route('seatcomlink::logs'))
            ->assertOk()
            ->assertSeeInOrder(['other driver', 'an error', 'first info']);

        $this->actingAs($this->admin)->get(route('seatcomlink::logs', ['level' => 'error']))
            ->assertSee('an error')->assertDontSee('first info')->assertDontSee('other driver');

        $this->actingAs($this->admin)->get(route('seatcomlink::logs', ['driver' => 'mumble']))
            ->assertSee('other driver')->assertDontSee('an error');

        $this->actingAs($this->admin)->get(route('seatcomlink::logs', ['from' => '2026-02-01', 'to' => '2026-02-28']))
            ->assertSee('an error')->assertDontSee('first info')->assertDontSee('other driver');

        $this->actingAs($this->admin)->get(route('seatcomlink::logs', ['level' => 'nope']))->assertSessionHasErrors('level');
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
