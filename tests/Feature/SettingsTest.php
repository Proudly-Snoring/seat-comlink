<?php

namespace ProudlySnoring\Seat\Comlink\Tests\Feature;

use ProudlySnoring\Seat\Comlink\Models\ComlinkLog;
use ProudlySnoring\Seat\Comlink\Models\DiscordServer;
use ProudlySnoring\Seat\Comlink\Services\ComlinkSettings;
use ProudlySnoring\Seat\Comlink\Tests\TestCase;
use Seat\Services\Models\Schedule;
use Seat\Web\Models\User;

class SettingsTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin', [
            'id' => 1001, 'name' => 'Admin Pilot', 'corp' => [2001, 'Pilots Inc', 'PILOT'],
        ], true);
    }

    private function validSettings(array $overrides = []): array
    {
        return array_merge([
            'client_id' => '123456789012345678',
            'client_secret' => 'secret',
            'bot_token' => 'token',
            'sync_rate' => 10,
            'log_retention' => 15,
        ], $overrides);
    }

    public function testProcedureVisibleWithoutCredentials()
    {
        $this->actingAs($this->admin)->get(route('seatcomlink::settings'))
            ->assertOk()
            ->assertSee('collapse show', false)
            ->assertSee('id="procedure_callback_url" value="' . route('seatcomlink::discord.callback') . '"', false)
            ->assertSee('Add Redirect', false)
            ->assertSee(trans('comlink::comlink.invite_bot_disabled'));
    }

    public function testProcedureMaskedWithCredentials()
    {
        ComlinkSettings::setCredentials('123456789012345678', 'secret', 'token');

        $this->actingAs($this->admin)->get(route('seatcomlink::settings'))
            ->assertOk()
            ->assertDontSee('collapse show', false)
            ->assertSee('client_id=123456789012345678', false)
            ->assertSee('permissions=402653184', false);
    }

    public function testSaveSettings()
    {
        $this->actingAs($this->admin)->post(route('seatcomlink::settings.update'), $this->validSettings())
            ->assertRedirect(route('seatcomlink::settings'));

        $this->assertSame('123456789012345678', ComlinkSettings::clientId());
        $this->assertSame('secret', ComlinkSettings::clientSecret());
        $this->assertSame('token', ComlinkSettings::botToken());
        $this->assertSame(10, ComlinkSettings::syncRate());
        $this->assertSame(15, ComlinkSettings::logRetention());
        $this->assertSame('*/10 * * * *', Schedule::where('command', 'comlink:discord:sync')->value('expression'));

        $log = ComlinkLog::where('message', 'Updated discord settings')->first();
        $this->assertSame('info', $log->level);
        $this->assertSame($this->admin->id, (int) $log->user_id);
    }

    public function testInvalidSettingsAreRejected()
    {
        foreach ([['sync_rate' => 7], ['sync_rate' => 0], ['log_retention' => 0], ['client_id' => 'abc']] as $invalid) {
            $this->actingAs($this->admin)->post(route('seatcomlink::settings.update'), $this->validSettings($invalid))
                ->assertSessionHasErrors(array_keys($invalid));
        }

        $this->assertSame(0, ComlinkLog::count());
    }

    public function testServersAreNotInSettings()
    {
        DiscordServer::create(['server_id' => '1', 'name' => 'Alliance']);

        $this->actingAs($this->admin)->get(route('seatcomlink::settings'))->assertOk()->assertDontSee('Alliance');
    }
}
