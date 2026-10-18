<?php

namespace ProudlySnoring\Seat\Comlink\Tests\Feature;

use ProudlySnoring\Seat\Comlink\Models\ComlinkLog;
use ProudlySnoring\Seat\Comlink\Models\DiscordServer;
use ProudlySnoring\Seat\Comlink\Tests\TestCase;
use Seat\Web\Models\User;

class ServersTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin', [
            'id' => 1001, 'name' => 'Admin Pilot', 'corp' => [2001, 'Pilots Inc', 'PILOT'],
        ], true);
    }

    public function testServersView()
    {
        DiscordServer::create(['server_id' => '1', 'name' => 'Alliance', 'last_synced_at' => '2026-10-18 14:05:09']);
        DiscordServer::create(['server_id' => '2', 'name' => 'Corp']);

        $this->actingAs($this->admin)->get(route('seatcomlink::servers'))
            ->assertOk()
            ->assertSeeInOrder(['Alliance', '2026-10-18 14:05:09', 'Corp', trans('comlink::comlink.never')])
            ->assertSee('<span class="comlink-preview">[PILOT] Admin Pilot</span>', false)
            // naming rules between the input and the tokens
            ->assertSeeInOrder(['name="nickname_format"', e(trans('comlink::comlink.nickname_rules', ['max' => 32])), '{character}'], false);
    }

    public function testNoServer()
    {
        $this->actingAs($this->admin)->get(route('seatcomlink::servers'))
            ->assertOk()->assertSee(trans('comlink::comlink.no_servers'));
    }

    public function testUpdateServer()
    {
        $server = DiscordServer::create(['server_id' => '1', 'name' => 'Alliance']);

        $this->actingAs($this->admin)->post(route('seatcomlink::servers.update', $server), [
            'enforce_nickname' => '1',
            'enforce_roles' => '0',
            'nickname_format' => '{character} ({corp_ticker})',
        ])->assertRedirect(route('seatcomlink::servers'));

        $server->refresh();
        $this->assertTrue($server->enforce_nickname);
        $this->assertFalse($server->enforce_roles);
        $this->assertSame('{character} ({corp_ticker})', $server->nickname_format);
        $this->assertSame(1, ComlinkLog::where('message', 'Updated discord settings')->count());

        $this->actingAs($this->admin)->post(route('seatcomlink::servers.update', $server), [
            'enforce_nickname' => '1',
            'nickname_format' => '',
        ])->assertSessionHasErrors('nickname_format');
    }

    public function testRemovedServersAreHidden()
    {
        DiscordServer::create(['server_id' => '1', 'name' => 'Gone Server', 'present' => false]);

        $this->actingAs($this->admin)->get(route('seatcomlink::servers'))->assertDontSee('Gone Server');
    }

    public function testPreview()
    {
        $this->actingAs($this->admin)
            ->getJson(route('seatcomlink::servers.preview', ['format' => '{alliance_ticker} | {character}']))
            ->assertExactJson(['nickname' => '- | Admin Pilot']);
    }
}
