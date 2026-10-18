<?php

namespace ProudlySnoring\Seat\Comlink\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ProudlySnoring\Seat\Comlink\Models\ComlinkLog;
use ProudlySnoring\Seat\Comlink\Models\DiscordUser;
use ProudlySnoring\Seat\Comlink\Tests\TestCase;

class ImportWarlofTest extends TestCase
{
    private function createWarlofTable(array $rows): void
    {
        Schema::create('seat_connector_users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('connector_type');
            $table->string('connector_id');
            $table->string('connector_name');
            $table->string('unique_id')->nullable();
            $table->unsignedInteger('group_id')->nullable();
            $table->unsignedInteger('user_id');
        });

        foreach ($rows as [$type, $connector_id, $name, $user_id]) {
            DB::table('seat_connector_users')->insert([
                'connector_type' => $type,
                'connector_id' => $connector_id,
                'connector_name' => $name,
                'user_id' => $user_id,
            ]);
        }
    }

    public function testMissingTable()
    {
        $this->artisan('comlink:discord:import-warlof')->assertSuccessful()
            ->expectsOutputToContain('not found');
    }

    public function testImport()
    {
        $alice = $this->makeUser('alice');
        $bob = $this->makeUser('bob');
        $carol = $this->makeUser('carol');
        $dave = $this->makeUser('dave');

        // dave is already linked to another account, carol is already linked to the same one
        DiscordUser::create(['user_id' => $dave->id, 'discord_id' => '400', 'username' => 'dave_new']);
        DiscordUser::create(['user_id' => $carol->id, 'discord_id' => '300', 'username' => 'carol_d']);

        $this->createWarlofTable([
            ['discord', '100', 'alice_d', $alice->id],
            ['discord', '100', 'alice_d', $bob->id],    // same discord account on 2 users: conflict
            ['discord', '200', 'bob_d', $bob->id],      // bob's own account
            ['discord', '300', 'carol_d', $carol->id],  // already linked
            ['discord', '401', 'dave_old', $dave->id],  // conflict: dave already linked
            ['discord', '500', 'ghost', 9999],          // unknown user
            ['teamspeak', '600', 'ts', $alice->id],     // other connector
        ]);

        $this->artisan('comlink:discord:import-warlof', ['--dry-run' => true])->assertSuccessful()
            ->expectsOutputToContain('Dry run');
        $this->assertSame(2, DiscordUser::count());

        $this->artisan('comlink:discord:import-warlof')->assertSuccessful();

        $links = DiscordUser::orderBy('user_id')->pluck('discord_id', 'user_id')->mapWithKeys(fn ($d, $u) => [(int) $u => $d])->all();
        $this->assertSame([
            $alice->id => '100',
            $bob->id => '200',
            $carol->id => '300',
            $dave->id => '400',
        ], $links);
        $this->assertSame('alice_d', DiscordUser::where('discord_id', '100')->value('username'));
        $this->assertSame(2, ComlinkLog::where('message', 'like', 'Linked discord account %')->count());

        // idempotent
        $this->artisan('comlink:discord:import-warlof')->assertSuccessful();
        $this->assertSame(4, DiscordUser::count());
        $this->assertSame(2, ComlinkLog::count());
    }
}
