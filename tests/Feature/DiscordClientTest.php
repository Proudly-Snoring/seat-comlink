<?php

namespace ProudlySnoring\Seat\Comlink\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordClient;
use ProudlySnoring\Seat\Comlink\Drivers\Discord\DiscordException;
use ProudlySnoring\Seat\Comlink\Services\ComlinkSettings;
use ProudlySnoring\Seat\Comlink\Tests\TestCase;

class DiscordClientTest extends TestCase
{
    const API = 'https://discord.com/api/v10';

    private array $sleeps = [];

    private function client(): DiscordClient
    {
        return new DiscordClient('bot-token', function (float $seconds) {
            $this->sleeps[] = $seconds;
        });
    }

    public function testAuthenticationHeaders()
    {
        Http::fake([self::API . '/users/@me' => Http::response(['id' => '1', 'username' => 'bot'])]);

        $this->assertSame('bot', $this->client()->me()['username']);

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Authorization', 'Bot bot-token')
                && str_starts_with($request->header('User-Agent')[0], 'DiscordBot (');
        });
    }

    public function testContainerUsesConfiguredToken()
    {
        $this->assertFalse(app(DiscordClient::class)->isConfigured());

        ComlinkSettings::setCredentials('1', 'secret', 'stored-token');
        Http::fake([self::API . '/users/@me' => Http::response(['id' => '1'])]);

        app(DiscordClient::class)->me();

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bot stored-token'));
    }

    public function testUnconfiguredClientThrows()
    {
        $this->expectException(DiscordException::class);

        (new DiscordClient(null))->me();
    }

    public function testMembersArePaginated()
    {
        $page1 = array_map(fn ($i) => ['user' => ['id' => (string) $i]], range(1, DiscordClient::MEMBERS_PAGE));
        $page2 = [['user' => ['id' => '5000']]];

        Http::fakeSequence(self::API . '/guilds/10/members*')->push($page1)->push($page2);

        $members = iterator_to_array($this->client()->members('10'), false);

        $this->assertCount(DiscordClient::MEMBERS_PAGE + 1, $members);

        $recorded = Http::recorded();
        $this->assertCount(2, $recorded);
        $this->assertStringContainsString('after=0', $recorded[0][0]->url());
        $this->assertStringContainsString('after=' . DiscordClient::MEMBERS_PAGE, $recorded[1][0]->url());
        $this->assertStringContainsString('limit=1000', $recorded[1][0]->url());
    }

    public function testRateLimitIsRetried()
    {
        Http::fakeSequence(self::API . '/guilds/10/roles')
            ->push(['message' => 'You are being rate limited.', 'retry_after' => 1.5, 'global' => false], 429)
            ->push([['id' => '10', 'name' => '@everyone']]);

        $roles = $this->client()->roles('10');

        $this->assertSame('@everyone', $roles[0]['name']);
        $this->assertSame([1.5], $this->sleeps);
    }

    public function testRateLimitGivesUpAfterRetries()
    {
        Http::fake([self::API . '/*' => Http::response(['message' => 'You are being rate limited.', 'retry_after' => 0.1], 429)]);

        try {
            $this->client()->roles('10');
            $this->fail('exception expected');
        } catch (DiscordException $e) {
            $this->assertSame(429, $e->status);
            $this->assertCount(3, $this->sleeps);
        }
    }

    public function testExhaustedBucketWaits()
    {
        Http::fake([self::API . '/guilds/10/roles' => Http::response([], 200, [
            'X-RateLimit-Remaining' => '0',
            'X-RateLimit-Reset-After' => '2.25',
        ])]);

        $this->client()->roles('10');

        $this->assertSame([2.25], $this->sleeps);
    }

    public function testErrorsAreMapped()
    {
        Http::fake([self::API . '/*' => Http::response(['message' => 'Missing Permissions', 'code' => 50013], 403)]);

        try {
            $this->client()->addRole('10', '20', '30');
            $this->fail('exception expected');
        } catch (DiscordException $e) {
            $this->assertSame(403, $e->status);
            $this->assertSame(DiscordException::MISSING_PERMISSIONS, $e->discord_code);
            $this->assertSame('Missing Permissions (50013)', $e->reason());
        }
    }

    public function testWriteEndpoints()
    {
        Http::fake([self::API . '/*' => Http::response(null, 204)]);

        $client = $this->client();
        $client->addRole('10', '20', '30');
        $client->removeRole('10', '20', '31');
        $client->setNickname('10', '20', '[CORP] Pilot');

        $recorded = Http::recorded()->map(fn ($pair) => $pair[0]->method() . ' ' . $pair[0]->url())->all();

        $this->assertSame([
            'PUT ' . self::API . '/guilds/10/members/20/roles/30',
            'DELETE ' . self::API . '/guilds/10/members/20/roles/31',
            'PATCH ' . self::API . '/guilds/10/members/20',
        ], $recorded);

        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH' && $request['nick'] === '[CORP] Pilot');
    }

    public function testGuildsArePaginated()
    {
        $page1 = array_map(fn ($i) => ['id' => (string) $i, 'name' => 'g' . $i], range(1, DiscordClient::GUILDS_PAGE));

        Http::fakeSequence(self::API . '/users/@me/guilds*')->push($page1)->push([['id' => '999', 'name' => 'last']]);

        $this->assertCount(DiscordClient::GUILDS_PAGE + 1, $this->client()->guilds());
        $this->assertStringContainsString('after=' . DiscordClient::GUILDS_PAGE, Http::recorded()[1][0]->url());
    }
}
