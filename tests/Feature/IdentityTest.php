<?php

namespace ProudlySnoring\Seat\Comlink\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ProudlySnoring\Seat\Comlink\Http\Controllers\IdentityController;
use ProudlySnoring\Seat\Comlink\Models\ComlinkLog;
use ProudlySnoring\Seat\Comlink\Models\DiscordUser;
use ProudlySnoring\Seat\Comlink\Services\ComlinkSettings;
use ProudlySnoring\Seat\Comlink\Tests\TestCase;
use Seat\Web\Models\User;

class IdentityTest extends TestCase
{
    const API = 'https://discord.com/api/v10';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        ComlinkSettings::setCredentials('123', 'secret', 'token');
        $this->user = $this->makeUser('pilot');
        $this->grant($this->user, 'comlink.identity');
    }

    private function fakeDiscord(string $id = '555', string $username = 'pilot_discord'): void
    {
        Http::fake([
            self::API . '/oauth2/token' => Http::response(['access_token' => 'user-token', 'token_type' => 'Bearer']),
            self::API . '/users/@me' => Http::response(['id' => $id, 'username' => $username, 'discriminator' => '0']),
        ]);
    }

    private function oauthCallback(array $session = [IdentityController::STATE_KEY => 'state-123'], array $query = ['code' => 'abc', 'state' => 'state-123'])
    {
        return $this->actingAs($this->user)->withSession($session)->get(route('seatcomlink::discord.callback', $query));
    }

    public function testUnlinkedCard()
    {
        $this->actingAs($this->user)->get(route('seatcomlink::identity'))
            ->assertOk()
            ->assertSee(trans('comlink::comlink.status_unknown'))
            ->assertSee(route('seatcomlink::discord.link'));
    }

    public function testLinkRedirectsToDiscord()
    {
        $response = $this->actingAs($this->user)->get(route('seatcomlink::discord.link'));

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://discord.com/oauth2/authorize?', $location);
        $this->assertStringContainsString('scope=identify', $location);
        $this->assertStringContainsString('client_id=123', $location);
        $this->assertStringContainsString('state=' . session(IdentityController::STATE_KEY), $location);
        $this->assertStringContainsString('redirect_uri=' . urlencode(route('seatcomlink::discord.callback')), $location);
    }

    public function testLinkRequiresConfiguration()
    {
        ComlinkSettings::setCredentials(null, null, null);

        $this->actingAs($this->user)->get(route('seatcomlink::discord.link'))
            ->assertRedirect(route('seatcomlink::identity'))
            ->assertSessionHas('error');
    }

    public function testCallbackLinksAccount()
    {
        $this->fakeDiscord();

        $this->oauthCallback()->assertRedirect(route('seatcomlink::identity'))->assertSessionHas('success');

        $link = DiscordUser::first();
        $this->assertSame($this->user->id, (int) $link->user_id);
        $this->assertSame('555', $link->discord_id);
        $this->assertSame('pilot_discord', $link->username);
        $this->assertSame(1, ComlinkLog::where('message', 'Linked discord account 555 with user ' . $this->user->id)->count());

        Http::assertSent(fn (Request $request) => $request->url() === self::API . '/oauth2/token'
            && $request['grant_type'] === 'authorization_code' && $request['code'] === 'abc');

        $this->actingAs($this->user)->get(route('seatcomlink::identity'))
            ->assertSee(trans('comlink::comlink.status_linked'))
            ->assertSee('pilot_discord');
    }

    public function testCallbackRejectsInvalidState()
    {
        $this->fakeDiscord();

        $this->oauthCallback([IdentityController::STATE_KEY => 'other'])->assertSessionHas('error');
        $this->oauthCallback([])->assertSessionHas('error');

        $this->assertSame(0, DiscordUser::count());
        Http::assertNothingSent();
    }

    public function testCallbackHandlesDiscordError()
    {
        Http::fake([self::API . '/oauth2/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->oauthCallback()->assertSessionHas('error');

        $this->assertSame(0, DiscordUser::count());
        $this->assertSame('error', ComlinkLog::first()->level);
    }

    public function testCallbackHandlesUserCancel()
    {
        $this->oauthCallback(query: ['error' => 'access_denied', 'state' => 'state-123'])->assertSessionHas('error');
    }

    public function testRelinkReplacesOwnPreviousAccount()
    {
        DiscordUser::create(['user_id' => $this->user->id, 'discord_id' => '111', 'username' => 'old']);
        $this->fakeDiscord('555');

        $this->oauthCallback();

        $this->assertSame(['555'], DiscordUser::pluck('discord_id')->all());
        $this->assertSame(1, ComlinkLog::where('message', 'Unlinked discord account 111 with user ' . $this->user->id)->count());
    }

    public function testConflictAsksForConfirmation()
    {
        $other = $this->makeUser('other');
        DiscordUser::create(['user_id' => $other->id, 'discord_id' => '555', 'username' => 'shared']);
        $this->fakeDiscord('555', 'shared');

        $this->oauthCallback()->assertRedirect(route('seatcomlink::identity'))->assertSessionHas(IdentityController::PENDING_KEY);

        // nothing changed yet
        $this->assertSame($other->id, (int) DiscordUser::first()->user_id);

        $this->actingAs($this->user)->get(route('seatcomlink::identity'))
            ->assertSee(trans('comlink::comlink.replace_title'))
            ->assertSee(route('seatcomlink::discord.link.confirm'));

        $this->actingAs($this->user)->post(route('seatcomlink::discord.link.confirm'))->assertSessionHas('success');

        $this->assertSame(1, DiscordUser::count());
        $this->assertSame($this->user->id, (int) DiscordUser::first()->user_id);

        $messages = ComlinkLog::orderBy('id')->pluck('message')->all();
        $this->assertSame([
            'Unlinked discord account 555 from user ' . $other->id,
            'Linked discord account 555 with user ' . $this->user->id,
        ], $messages);
    }

    public function testConflictCancel()
    {
        $other = $this->makeUser('other');
        DiscordUser::create(['user_id' => $other->id, 'discord_id' => '555', 'username' => 'shared']);
        $this->fakeDiscord('555');

        $this->oauthCallback();
        $this->actingAs($this->user)->post(route('seatcomlink::discord.link.cancel'))->assertSessionMissing(IdentityController::PENDING_KEY);

        // confirming afterwards does nothing
        $this->actingAs($this->user)->post(route('seatcomlink::discord.link.confirm'));

        $this->assertSame($other->id, (int) DiscordUser::first()->user_id);
        $this->assertSame(0, ComlinkLog::count());
    }

    public function testUnlink()
    {
        DiscordUser::create(['user_id' => $this->user->id, 'discord_id' => '555', 'username' => 'me']);

        $this->actingAs($this->user)->get(route('seatcomlink::identity'))
            ->assertSee('<dt class="col-5 text-right text-nowrap">' . trans('comlink::comlink.discord_username') . '</dt>', false)
            ->assertSee('card-footer  text-right', false)
            ->assertSee(trans('comlink::comlink.unlink_confirm', ['username' => 'me']) . '<br>', false)
            ->assertSee('<i class="fas fa-exclamation-triangle text-warning"></i> ' . trans('comlink::comlink.unlink_confirm_roles'), false);

        $this->actingAs($this->user)->post(route('seatcomlink::discord.unlink'))->assertSessionHas('success');

        $this->assertSame(0, DiscordUser::count());
        $this->assertSame(1, ComlinkLog::where('message', 'Unlinked discord account 555 with user ' . $this->user->id)->count());
    }

    public function testLegacyDiscriminator()
    {
        $this->assertSame('john#1234', \ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordOAuth::username(['username' => 'john', 'discriminator' => '1234']));
        $this->assertSame('john', \ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordOAuth::username(['username' => 'john', 'discriminator' => '0']));
    }
}
