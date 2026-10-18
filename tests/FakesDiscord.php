<?php

namespace ProudlySnoring\Seat\Comlink\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Stateful fake of the discord REST API: writes (roles, nicknames) are applied to the fake state.
 */
trait FakesDiscord
{
    protected array $fake_guilds = [];

    protected array $fake_failures = [];

    protected array $fake_writes = [];

    protected string $fake_bot_id = '900';

    /**
     * @param  array  $roles  [id => [name, position, managed?]]
     * @param  array  $members  [user id => [username, nick, roles[], bot?]]
     */
    protected function addFakeGuild(string $id, string $name, array $roles = [], array $members = [], string $owner_id = '1', array $bot_roles = []): void
    {
        $roles = [$id => ['@everyone', 0]] + $roles;

        $this->fake_guilds[$id] = [
            'name' => $name,
            'owner_id' => $owner_id,
            'roles' => collect($roles)->map(fn ($role, $role_id) => [
                'id' => (string) $role_id,
                'name' => $role[0],
                'position' => $role[1],
                'color' => 0,
                'managed' => $role[2] ?? false,
            ])->values()->all(),
            'members' => collect($members)->mapWithKeys(fn ($member, $user_id) => [(string) $user_id => [
                'user' => ['id' => (string) $user_id, 'username' => $member[0], 'bot' => $member[3] ?? false],
                'nick' => $member[1] ?? null,
                'roles' => array_map('strval', $member[2] ?? []),
            ]])->put($this->fake_bot_id, [
                'user' => ['id' => $this->fake_bot_id, 'username' => 'comlink', 'bot' => true],
                'nick' => null,
                'roles' => array_map('strval', $bot_roles),
            ])->all(),
        ];
    }

    /**
     * Make a write call fail. Ex: failFake('PUT', '/guilds/1/members/2/roles/3', 403, 50013, 'Missing Permissions').
     */
    protected function failFake(string $method, string $path, int $status = 403, int $code = 50013, string $message = 'Missing Permissions'): void
    {
        $this->fake_failures[$method . ' ' . $path] = [$status, $code, $message];
    }

    protected function fakeMember(string $guild, string $user): array
    {
        return $this->fake_guilds[$guild]['members'][$user];
    }

    protected function installDiscordFake(): void
    {
        Http::fake(function (Request $request) {
            $path = substr(parse_url($request->url(), PHP_URL_PATH), strlen('/api/v10'));
            $method = $request->method();
            $key = $method . ' ' . $path;

            if ($method !== 'GET')
                $this->fake_writes[] = $key . ($method === 'PATCH' ? ' ' . $request['nick'] : '');

            if (isset($this->fake_failures[$key])) {
                [$status, $code, $message] = $this->fake_failures[$key];

                return Http::response(['message' => $message, 'code' => $code], $status);
            }

            if ($path === '/users/@me')
                return Http::response(['id' => $this->fake_bot_id, 'username' => 'comlink', 'bot' => true]);

            if ($path === '/users/@me/guilds') {
                return Http::response(collect($this->fake_guilds)
                    ->map(fn ($guild, $id) => ['id' => (string) $id, 'name' => $guild['name'], 'icon' => null])->values()->all());
            }

            if (! preg_match('#^/guilds/(\d+)(?:/(roles|members)(?:/(\d+)(?:/roles/(\d+))?)?)?$#', $path, $m))
                return Http::response(['message' => 'Unknown route ' . $key, 'code' => 0], 404);

            $guild = $m[1];
            $section = $m[2] ?? '';
            $user = $m[3] ?? null;
            $role = $m[4] ?? null;

            if (! isset($this->fake_guilds[$guild]))
                return Http::response(['message' => 'Unknown Guild', 'code' => 10004], 404);

            if ($section === '')
                return Http::response(['id' => $guild, 'name' => $this->fake_guilds[$guild]['name'], 'icon' => null, 'owner_id' => $this->fake_guilds[$guild]['owner_id']]);

            if ($section === 'roles')
                return Http::response($this->fake_guilds[$guild]['roles']);

            if (is_null($user)) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $after = $query['after'] ?? '0';
                $limit = (int) ($query['limit'] ?? 1);

                $members = collect($this->fake_guilds[$guild]['members'])
                    ->filter(fn ($member, $id) => (int) $id > (int) $after)
                    ->sortKeys()->take($limit)->values()->all();

                return Http::response($members);
            }

            if (! isset($this->fake_guilds[$guild]['members'][$user]))
                return Http::response(['message' => 'Unknown Member', 'code' => 10007], 404);

            $member = &$this->fake_guilds[$guild]['members'][$user];

            if ($method === 'GET')
                return Http::response($member);

            if ($method === 'PATCH') {
                $member['nick'] = $request['nick'];

                return Http::response($member);
            }

            if ($method === 'PUT')
                $member['roles'] = array_values(array_unique(array_merge($member['roles'], [$role])));

            if ($method === 'DELETE')
                $member['roles'] = array_values(array_diff($member['roles'], [$role]));

            return Http::response(null, 204);
        });
    }
}
