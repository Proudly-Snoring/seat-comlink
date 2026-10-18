# seat-comlink

A [SeAT](https://github.com/eveseat/seat) plugin that manages Discord members from SeAT data:
it assigns Discord **roles** based on SeAT **squads** and enforces Discord **nicknames** based on the users' characters
(ex: `[TICKR] Character Name`), on **one or more** Discord servers.

## How it works

1. An admin creates a Discord application with a bot, enters its credentials in SeAT and invites the bot to one or more Discord servers.
2. Admins map SeAT squads to Discord roles, per server (`Comlink > Discord Mappings`).
   A user gets a role if they belong to at least one squad mapped to it.
3. Users link their Discord account from `Comlink > Identity` ("Link my discord" button, Discord OAuth, `identify` scope only).
   Users must join the Discord servers by themselves.
4. At a regular interval (1 to 60 minutes, configurable), the bot polls each server through the Discord REST API and, for every non-bot member:
    * **linked** member: adds/removes the managed roles to match their squads, and enforces their nickname,
    * **unknown** member: removes all managed roles.

Only mapped roles are managed: other roles are never touched.
Nickname and role enforcement can be disabled per server.
Every action is recorded in `Comlink > Logs`.

### Nickname format

The format is defined per server. Default: `[{corp_ticker}] {main_name}`.

| Token               | Value                                            |
| ------------------- | ------------------------------------------------ |
| `{main_name}`       | Main character name (first character if no main) |
| `{corp_ticker}`     | Main character's corporation ticker              |
| `{corp_name}`       | Main character's corporation name                |
| `{alliance_ticker}` | Main character's alliance ticker                 |

Missing values are replaced by `-`.
Nicknames follow Discord naming rules: extra whitespaces and zero-width/non-rendering characters are removed, then the result is truncated to 32 characters.

### Views and permissions

| View                         | Purpose                                    | Permission         |
| ---------------------------- | ------------------------------------------ | ------------------ |
| `Comlink > Identity`         | Link / unlink one's Discord account        | `comlink.identity` |
| `Comlink > Discord Mappings` | Squads -> roles mapping, per server        | `comlink.mappings` |
| `Comlink > Discord Servers`  | Per-server options, nickname format        | `comlink.servers`  |
| `Comlink > Settings`         | Bot credentials, sync rate, logs retention | `comlink.settings` |
| `Comlink > Logs`             | Plugin logs                                | `comlink.logs`     |
| `Comlink > Users`            | Linked users and their Discord state       | `comlink.users`    |

Grant them from `Settings > Access Management`. Give `comlink.identity` to the role(s) of all users who should be able to link their account.

## Requirements

* SeAT 5.x (PHP 8.1+, Laravel 10).
* A Discord application with a bot.

## Installation

### 1. Install the package

#### Docker installation

In your `seat-docker` directory (ex: `/opt/seat-docker`), add the package to `SEAT_PLUGINS` in the `.env` file (comma separated):

```bash
SEAT_PLUGINS=proudly-snoring/seat-comlink
```

Then restart the stack with your usual compose files, for example:

```bash
# Traefik
docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.traefik.yml up -d
# Reverse proxy
docker compose -f docker-compose.yml -f docker-compose.mariadb.yml -f docker-compose.proxy.yml up -d
```

The plugin is installed, migrated and scheduled when the containers start. Follow progress with `docker compose logs --tail 10 -f`.

#### Standalone (bare metal) installation

From the SeAT directory (ex: `/var/www/seat`):

```bash
sudo -H -u www-data bash -c 'php artisan down'
sudo -H -u www-data bash -c 'composer require proudly-snoring/seat-comlink'
sudo -H -u www-data bash -c 'php artisan vendor:publish --force --all'
sudo -H -u www-data bash -c 'php artisan migrate'
sudo -H -u www-data bash -c 'php artisan route:cache'
sudo -H -u www-data bash -c 'php artisan config:cache'
sudo -H -u www-data bash -c 'php artisan seat:cache:clear'
sudo -H -u www-data bash -c 'php artisan db:seed --class=Seat\\Services\\Database\\Seeders\\PluginDatabaseSeeder'
sudo -H -u www-data bash -c 'php artisan up'
```

Then restart your queue workers (supervisor) so they load the plugin.

### 2. Create the Discord application

1. Go to the [Discord developer portal](https://discord.com/developers/applications) and create an application.
2. In **OAuth2**, copy the **Client ID** and **Client Secret**.
   Then in **Redirects**, click **Add Redirect**, paste the callback URL displayed in `Comlink > Settings`
   (`https://<your-seat>/seat-comlink/discord/callback`) and click **Save Changes**.
   Without it, users get an "Invalid OAuth2 redirect_uri" error when linking their account.
3. In **Bot**:
    * reset and copy the **Token**,
    * enable the **Server Members Intent** (privileged intent, required to list members),
    * optionally disable **Public Bot**.

### 3. Configure the plugin

1. In `Comlink > Settings`, enter the Client ID, Client Secret and Bot Token, then save.
2. Click **Invite bot** and add it to each Discord server (requires the *Manage Roles* and *Manage Nicknames* permissions).
3. On each Discord server, drag the bot's role **above** every role it must manage (Discord hierarchy rule).
4. In `Comlink > Discord Servers`, adjust the per-server options (the server appears after the next synchronization, or after "Import roles now" in the mappings).
5. Create the mappings in `Comlink > Discord Mappings`.
6. Grant the `comlink.*` permissions in `Settings > Access Management`.

## Artisan commands

Docker: prefix the commands with `docker compose exec front` (ex: `docker compose exec front php artisan comlink:discord:sync`).
Bare metal: run them as `www-data` from the SeAT directory.

| Command                                     | Description                                                                                                                                                                                                                                  |
| ------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `php artisan comlink:discord:sync`          | Queues a synchronization of all Discord servers. Options: `--server=<id>` to sync a single server, `--now` to run synchronously instead of queueing (debug). Scheduled automatically at the configured rate.                                 |
| `php artisan comlink:discord:import-warlof` | Imports the users' Discord links from [seat-discord-connector](https://github.com/warlof/seat-discord-connector). Conflicting links are skipped and reported. Option: `--dry-run` to only report. Credentials and policies are not imported. |
| `php artisan comlink:logs:prune`            | Deletes logs older than the retention setting (default 30 days). Option: `--days=<n>` to override. Scheduled daily.                                                                                                                          |

## Migrating from seat-discord-connector

Both plugins can be installed side by side.

1. Install and configure seat-comlink (you may reuse the same Discord application, adding the new redirect URL).
2. Run `php artisan comlink:discord:import-warlof --dry-run`, then without `--dry-run`.
3. Recreate the access rules as squads + mappings (warlof's corporation/alliance/title filters map to squad filters).
4. Uninstall seat-discord-connector, so that both bots do not fight over the same roles.

## Troubleshooting

* `Failed to update nickname/role ... Missing Permissions`: the bot's role is below the target role or member. Move it up in the server's role list.
  The server owner's nickname can never be changed by a bot.
* `Failed to synchronize server ... Missing Access (50001)`: the **Server Members Intent** is not enabled on the bot.
* Nothing happens: check the bot token in `Comlink > Settings`, then run `php artisan comlink:discord:sync --now` and read `Comlink > Logs`.

## Development

```bash
composer update
vendor/bin/phpunit
```

Tests run on Testbench with an in-memory sqlite database and a stateful fake of the Discord API (no network).

## License

GPL-2.0-or-later, like SeAT.
