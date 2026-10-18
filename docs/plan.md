# Implementation plan

Functional reference: [specifications.md](specifications.md).

## Conventions

| Item             | Value                                                                                               |
| ---------------- | --------------------------------------------------------------------------------------------------- |
| Composer package | `proudly-snoring/seat-comlink` (`type: seat-plugin`)                                                |
| PHP namespace    | `ProudlySnoring\Seat\Comlink\` -> `src/`                                                            |
| Service provider | `ComlinkServiceProvider` extends `Seat\Services\AbstractSeatPlugin`                                 |
| Routes           | prefix `seat-comlink`, names `seatcomlink::*`, middleware `web`, `auth`, `locale`                   |
| Views / lang     | namespace `comlink`                                                                                 |
| Tables           | prefix `comlink_`                                                                                   |
| Settings         | `proudly-snoring.comlink.*` (global settings via `setting([key, value], true)`)                     |
| Permissions      | scope `comlink`: `identity`, `mappings`, `settings`, `logs`, `users`                                |
| Artisan          | `comlink:*`                                                                                         |
| Models           | extend `Seat\Services\Models\ExtensibleModel`                                                       |
| Targets          | SeAT 5.x, PHP `^8.1`, Laravel `^10`                                                                 |
| Tests            | PHPUnit 10, `orchestra/testbench` 8, Mockery (same as `eveseat/web`), GitHub Actions on PHP 8.1/8.2 |
| Code style       | StyleCI, `.styleci.yml` copied from `eveseat/web` (laravel preset), GPL-2.0 header like SeAT files  |

Rules (SeAT dev tips): no FK nor changes on core tables; react to core models through observers;
schedules through `AbstractScheduleSeeder`.

## Data model

| Table                      | Columns (main)                                                                                                                                | Notes                                                             |
| -------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------- |
| `comlink_discord_users`    | `user_id` (unique), `discord_id` (unique), `username`, timestamps                                                                             | Link SeAT user <-> discord account                                |
| `comlink_discord_servers`  | `server_id` (unique), `name`, `icon`, `enforce_nickname`, `enforce_roles`, `nickname_format`, `bot_top_position`, `present`, `last_synced_at` | `present=false` when bot removed (keeps settings if re-invited)   |
| `comlink_discord_roles`    | `server_id`, `role_id` (unique), `name`, `position`, `managed`, `deleted_at`                                                                  | Cache refreshed at each sync; `deleted_at` = orphan               |
| `comlink_discord_mappings` | `server_id`, `squad_id`, `role_id`, unique(`server_id`,`squad_id`,`role_id`)                                                                  |                                                                   |
| `comlink_discord_members`  | `server_id`, `discord_id`, `nickname`, `seen_at`                                                                                              | Linked members only; feeds Users view (per-server nickname)       |
| `comlink_discord_issues`   | `server_id`, `discord_id`, `kind` (nickname/role), `target` (role id or null), `reason_hash`, `created_at`                                    | Any member (linked or not); warning dedup, row deleted on success |
| `comlink_logs`             | `level`, `driver`, `message`, `user_id` (nullable), `context` (json), `created_at`                                                            | Indexed on `created_at`, `level`, `driver`                        |

Secrets (client secret, bot token) stored encrypted (`Crypt::encryptString`) in global settings.

## Architecture

```
src/
├── ComlinkServiceProvider.php
├── Config/                       # sidebar, permissions, comlink.config.php
├── Commands/                     # Sync, ImportWarlof, PruneLogs
├── Drivers/
│   ├── DriverInterface.php       # future VOIP drivers (identity card + sync)
│   └── Discord/
│       ├── DiscordClient.php     # REST wrapper on Laravel Http (auth, pagination, 429 retry)
│       ├── DiscordOAuth.php      # identify flow (Socialite provider or plain Http)
│       ├── NicknameRenderer.php  # tokens, dash fallback, discord normalization
│       └── RoleResolver.php      # user -> squads -> expected managed roles per server
├── Jobs/                         # DiscoverServers, SyncServer
├── Http/{Controllers,Validation,DataTables}/
├── Models/
├── Observers/                    # UserObserver, SquadObserver
├── Services/ComlinkLogger.php    # writes comlink_logs (+ Laravel log for errors)
├── database/{migrations,seeders}/
└── resources/{views,lang}/
```

Discord REST endpoints (API v10):
* `GET /users/@me/guilds` - bot servers (discovery, invited/removed detection),
* `GET /guilds/{id}/roles` - roles cache, bot top position,
* `GET /guilds/{id}/members?limit=1000&after=` - paginated members (needs Server Members intent),
* `PUT|DELETE /guilds/{id}/members/{user}/roles/{role}` - per-role add/remove (no overwrite of unmanaged roles, per-role error logs),
* `PATCH /guilds/{id}/members/{user}` with `nick`,
* OAuth: `https://discord.com/oauth2/authorize` (`identify`), `POST /oauth2/token`, `GET /users/@me`.
* Invite URL: `scope=bot&permissions=402653184` (Manage Roles + Manage Nicknames).

## Sync algorithm

`comlink:discord:sync` -> `DiscoverServers` job:
1. List bot servers, upsert `comlink_discord_servers`, log invited/removed by diff with `present`.
2. Dispatch one `SyncServer` job per present server (unique per server: `ShouldBeUnique`, no overlap).

`SyncServer(server)`:
1. Refresh roles cache; newly missing mapped roles -> `deleted_at`, error log once.
2. Preload: links (`discord_id -> user`), mappings, squad memberships (`Seat\Web\Models\Squads\Squad` / pivot `squad_member`, bypass `SquadScope` global scope), main characters + corp/alliance info.
3. Page members; for each non-bot member:
    * managed = non-orphan mapped roles of the server; expected = managed roles from user squads (empty if unknown),
    * if `enforce_roles`: add `expected - current`, remove `(current ∩ managed) - expected`,
    * if linked and `enforce_nickname`: render nickname; if member above bot or owner -> warning; else PATCH when different,
    * failures -> warning (discord username instead of character for unknown users), logged only if no matching `comlink_discord_issues` row; row deleted on success,
    * upsert `comlink_discord_members` for linked members; delete rows not seen.
4. `last_synced_at = now()`.

Rate limits: honour `X-RateLimit-*` / `429 retry_after` in `DiscordClient`; jobs on the default SeAT queue with generous timeout.

Schedule: `ComlinkScheduleSeeder` seeds `comlink:discord:sync` (`*/5 * * * *`) and `comlink:logs:prune` (daily).
Rate picked among divisors of 60 (1, 2, 3, 4, 5, 6, 10, 12, 15, 20, 30, 60) so `*/N` stays regular.
Saving the rate updates the `Seat\Services\Models\Schedule` row's `expression` (`*/N * * * *`, `0 * * * *` for 60).
The seeder only inserts missing commands (verified in `AbstractScheduleSeeder::run()`), so restarts keep the admin's rate.

`NicknameRenderer` output is normalized like Discord does (strip zero-width/non-rendering chars, trim + collapse whitespaces, truncate to 32, `-` if empty)
and compared to the current nick after the same normalization, to avoid useless PATCH calls at each sync.

"Import roles now" (Mappings view): runs `DiscoverServers` + roles refresh synchronously (few calls), no members sync.

## Milestones

Each milestone ends with tests green and a commit.

1. **Skeleton**
   `composer.json`, service provider (routes, views, lang, migrations, sidebar, permissions, seeders), empty pages, `phpunit.xml`, Testbench base test case, GitHub Actions workflow, LICENSE.
2. **Data + logging**
   Migrations, models, `ComlinkLogger`, `UserObserver` (delete link), `SquadObserver` (delete mappings + log), `comlink:logs:prune` + retention setting.
3. **Settings view**
   Bot creation procedure (collapsible, open by default only if credentials unset), credentials form (masked/reveal, encrypted storage), callback URL, invite button, sync rate (list, updates schedule), retention, servers list with per-server options, live nickname preview + naming rules warning, "sync now" button.
4. **Discord client**
   `DiscordClient` with pagination, rate-limit handling, error mapping; `Http::fake` tests.
5. **Identity view + OAuth**
   Link flow (state/CSRF check), conflict confirmation (link replacement), unlink confirmation, logs.
6. **Mappings view**
   DataTable with filters, add/edit/delete modal (server, squad, role pickers, unmanageable roles shown disabled with reason), orphan badge, "Import roles now" button, logs.
7. **Sync engine**
   `NicknameRenderer`, `RoleResolver`, `DiscoverServers`, `SyncServer`, `comlink:discord:sync`, schedule seeder. Heaviest test coverage (role diff, bots skipped, unknown users, hierarchy warnings, dedup, orphans, enforce flags).
8. **Logs + Users views**
   DataTables with filters (level, driver, date range) and pagination; Users from links + members.
9. **Warlof import**
   `comlink:discord:import-warlof [--dry-run]`: reads `seat_connector_users` where `connector_type = 'discord'` (`user_id`, `connector_id`, `connector_name`), skips conflicts, reports counts; no-op with message if table missing.
10. **Release**
    README check, manual test on a SeAT docker dev stack with a test Discord server, tag, Packagist.

## To verify during implementation

* `SquadScope` behaviour in queue context (no authenticated user) - bypass if it filters.
* `setting()` global write signature and caching (`Seat\Services\Settings\Seat`).
* Exact `AbstractSeatPlugin` abstract methods (`getName`, `getPackageRepositoryUrl`, `getPackagistPackageName`, `getPackagistVendorName`).
* SeAT queue names (`seat-queues.php`) to pick the right queue.
* Character/corporation/alliance models for tokens (`Seat\Eveapi\Models\Character\CharacterInfo`, `CharacterAffiliation`, `Corporation\CorporationInfo`, `Alliances\Alliance`).
* Socialite already shipped by `eveseat/web` (`laravel/socialite`, `socialiteproviders/manager`): reuse with `socialiteproviders/discord` vs plain Http.
