## Core features

* SeAT admins define a map of SeAT "Squads" to discord "roles".
    * The roles may differ on each discord server, so there is one mapping per discord server.
    * The mapping is many-to-many: a squad may grant several roles and a role may be granted by several squads.
    * A user gets a managed role if they are a member of **at least one** squad mapped to this role.
* Users link their discord account with their SeAT account.
    * A command to import data from a preinstalled [seat-discord-connector](https://github.com/warlof/seat-discord-connector) is also provided.
* The plugin regularly enforce registered users' nickname and roles:
    * It enforce the user's nickname based on a format defined in configuration (ex: `[{corp_ticker}] {character}`).
    * It adds or removes the managed discord roles to each registered user (when needed).
    * It removes the managed discord roles from any user that is not registered on SeAT.
    * Only the discord roles defined in the SeAT are managed. Other roles are untouched.
* Bots members of the discord servers are always ignored (never renamed, roles never changed).

## How it works

* The plugin works as a discord bot invited to one or more discord servers.
    * The bot only uses the discord REST API, polled at each synchronization (no permanent connection to discord).
    * The bot requires the "Server Members" privileged intent, and the "Manage Roles" and "Manage Nicknames" permissions on each server.
    * The bot's role must be placed above every managed role (discord hierarchy rule).
* It maps users' discord ID to their SeAT account and SeAT Squads to discord roles.
* A regular time interval (ex: every 5 minutes), it lists the servers where the bot is present, their roles, their users and the users' roles. For each discord user:
    * If the user is a bot, it is skipped.
    * If the user ID is known, it synchronizes the managed roles the user should have based on their squads.
    * If the user ID is unknown, it removes all the managed roles.
    * If the user ID is known, it also enforces their nickname.
* Changes detected at each synchronization:
    * A server that appears / disappears from the bot's servers list is logged as an invitation / removal.
    * A role that has been deleted on discord marks its mappings as "orphan" (displayed as such in the mapping view and ignored by the synchronization), and an error is logged.
* Users link their discord account with their SeAT account via the OAuth protocol.
    * In the SeAT, they click on a "link my discord" button. This triggers the OAuth flow.
    * The OAuth flow only requests the `identify` scope: the plugin never joins users to servers, they must join the servers by themselves.
    * After the OAuth flow is complete, the SeAT gets and records the user's discord account ID and username.
      This is what is used to recognize the user on discord.
* Linking rules:
    * One SeAT user is linked to at most one discord account, and one discord account to at most one SeAT user.
    * If the discord account is already linked to another SeAT user, a confirmation box explains that the previous link will be deleted.
      On confirmation, the old link is deleted and the new one is recorded. On cancellation, nothing changes.
    * Unlinking requires a confirmation box asking to unlink the account, then on a second line, with a warning icon, that the managed roles will be removed at the next synchronization.
      The roles are removed at the next synchronization (not immediately).
* Deleting a SeAT user deletes their discord link. Deleting a SeAT squad deletes its mappings (logged as a mapping removal).

## Nicknames

* The nickname is built from a "selected character": the user's main character or, if none, the first character linked to their SeAT account.
* The nickname format accepts the following tokens:
    * `{character}`: the name of the selected character,
    * `{corp_ticker}`: the ticker of the selected character's corporation,
    * `{corp_name}`: the name of the selected character's corporation,
    * `{alliance_ticker}`: the ticker of the selected character's alliance.
* Default format: `[{corp_ticker}] {character}`.
* Any token without a value (ex: no alliance, no character) is replaced by a single dash (`-`).
* Discord naming rules for nicknames:
    * 1 to 32 characters,
    * leading, trailing and repeated internal whitespaces are removed,
    * some zero-width and non-rendering characters are forbidden.
* At synchronization, the rendered nickname is adapted to these rules: forbidden characters and extra whitespaces removed, then truncated to 32 characters.
  It is compared to the current nickname after this adaptation, so it is only updated when it actually differs.
* In the Discord Servers view, an information note between the format input and the available tokens explains these rules and that longer nicknames will be truncated.
* The nickname cannot be enforced on users ranked above the bot (including the server owner): a warning is logged (see Logs).

## Views

All views are grouped in a "Comlink" sidebar menu, in this order: Identity, Discord Mappings, Discord Servers, Users, Logs, Settings.

* Comlink/Identity
    * Displays a discord card with:
        * The "Discord" title with discord icon,
        * A "status" badge ("Linked" green if  user's ID is known / "Unknown" red else),
        * The connected user's username and ID on discord (if already known), each label and value on the same line,
        * The "link my discord" button if user's ID is not already known.
        * A "unlink" button, on the right of the card footer, if user's ID is known (with a confirmation box, see Linking rules).
    * May display other cards for other VOIP services (called "drivers") in the future.
    * Intended for all users (configurable from the standard SeAT roles management).
* Comlink/Discord Mappings
    * Displays a table of roles mapping with the following columns:
        * Discord server name,
        * Squad name, followed by its number of SeAT accounts in parenthesis,
        * Role name, followed by the number of discord accounts holding it at the last synchronization in parenthesis
          (with an "orphan" badge if the role has been deleted on discord),
        * An "Edit" button,
        * A "Delete" button.
    * The table must be filterable by discord server, squad name and role name.
    * The card footer holds the following buttons: "Import roles now" and "Sync now" on the left, "Add mapping" on the right.
    * The "Add mapping" button allows to add more rows.
        * The server, squad and role are picked from lists (roles as known at the last synchronization).
        * Roles that the bot cannot manage (above the bot's role, or managed by an integration) are displayed but not selectable, with the reason.
    * The "Import roles now" button refreshes the servers and roles lists from discord immediately (without waiting for the next synchronization).
    * The "Sync now" button forces the synchronization of all the discord servers now (queued, runs in background).
    * Intended for admins only (configurable from the standard SeAT roles management).
* Comlink/Discord Servers
    * The list of discord servers where the bot is currently present, one card for each with:
        * The server icon, name and ID,
        * The datetime of the last synchronization (or "never"),
        * A "Enforce nickname" checkbox - If checked (default), the bot enforce the discord nicknames for this server, else it doesn't touch it.
        * A "Enforce roles" checkbox - If checked (default), the bot enforce the discord roles for this server, else it doesn't touch it.
        * The nickname format (if "Enforce nickname" is checked) - default is `[{corp_ticker}] {character}`:
            * its label includes a live preview for the current admin (ex: "Nickname format - ex: [PR.SN] Ryanis"),
            * below the input, the naming rules (see Nicknames), then the available tokens, each in its own information block.
        * A "Save" button in the card footer, on the right.
    * Intended for admins only (configurable from the standard SeAT roles management).
* Comlink/Users
    * Displays a table of known discord users with the following columns:
        * The SeAT user ID,
        * The user's name (SeAT main character),
        * The discord ID,
        * The discord username,
        * The discord servers where the user is present (as seen at the last synchronization),
          each with the nickname enforced on this server (the format is defined per server).
    * Intended for admins only (configurable from the standard SeAT roles management).
* Comlink/Logs
    * The plugin logs in table format, sorted from newer to older with pagination and the following columns:
        * Datetime of the log,
        * Log level (debug/info/warning/error),
        * The driver name (only "discord" for now),
        * The actual log message (plain text).
    * The table must be filterable by log level, driver name, and date range (two date inputs explicitly labelled "From" and "To").
    * Intended for admins only (configurable from the standard SeAT roles management).
* Comlink/Settings
    * The plugin settings with:
        * A short procedure explaining how to create the discord application and its bot
          (create the application, copy the client ID/secret, register the callback URL, create the bot token,
          enable the "Server Members" intent, invite the bot, move the bot's role above the managed roles).
            * The callback URL step details where to register it in the discord portal (OAuth2, Redirects, Add Redirect, Save Changes),
              and shows the URL with a copy button.
            * A button masks/unmasks the procedure.
            * It is visible by default if the credentials are not set, masked by default otherwise.
        * The discord bot credentials (client ID, client secret, bot token - editable - secrets masked by default but with a button to reveal them),
        * The OAuth callback URL to register in the discord application (read only, with a copy button),
        * An "Invite bot" button opening the discord invitation page with the required permissions,
        * The rate of the synchronization, picked from a list: 1, 2, 3, 4, 5 (default), 6, 10, 12, 15, 20, 30 or 60 minutes,
        * The logs retention (in days, default 30).
    * Intended for admins only (configurable from the standard SeAT roles management).

Each view must have a corresponding entry in the SeAT Access Management configuration.

## Logs

* When an admin changes a mapping: `Added/edited/removed mapping SQUAD_NAME -> ROLE_NAME for discord server SERVER_NAME` (level info).
* When an admin changes the settings: `Updated discord settings` (level info).
* When an admin clicks on "Import roles now": `Imported discord roles from N servers` (level info).
* When an admin clicks on "Sync now": `Requested a manual synchronization of all discord servers` (level info).
* When a role is added/removed or a nickname is updated: `Added/Removed role ROLE_NAME to/from user ...` / `Updated nickname of user ... to NICKNAME` (level info).
* At the end of each server synchronization: `Synchronized server DISCORD_SERVER: N updates (A roles added, R roles removed, K nicknames updated, F failures), started at START, ended at END` (level debug).
* When a user links/unlinks : `Linked/Unlinked discord account DISCORD_ID with user USER_ID` (level info).
* When a link replaces a previous one: `Unlinked discord account DISCORD_ID from user OLD_USER_ID` (level info), followed by the link log.
* When the bot is added/removed to/from a discord server: `Invited/Removed to/from server DISCORD_SERVER` (level info).
* When a mapped role is deleted on discord: `Role ROLE_NAME deleted from server DISCORD_SERVER, its mappings are now orphan` (level error).
* When a nickname or role cannot be updated (usually due to a permission error or a user ranked above the bot): `Failed to update nickname/role for user SEAT_CHARACTER_NAME (discord id: DISCORD_ID) on server DISCORD_SERVER: REASON` (level warning).
    * For unknown (not linked) users, `SEAT_CHARACTER_NAME` is replaced by their discord username.
    * The same warning is not repeated at each synchronization: it is logged again only after the situation has been resolved then reappeared.
* Any other error is logged with error level.

Each log also includes the event datetime and the user at the origin of the event as metadata.
* In case of synchronization errors, the user is null.

Logs older than the retention setting (default 30 days) are deleted daily.
SeAT has no global retention setting for plugin logs, hence this dedicated setting.

## Import from seat-discord-connector

* A command imports the users' links from a preinstalled seat-discord-connector (discord ID, username and SeAT user).
* Links conflicting with an existing link (same SeAT user or same discord ID) are skipped and reported.
* The bot credentials and the seat-discord-connector policies are not imported:
  its policies are based on corporation/alliance/title/role filters, which cannot be converted to squads.
  Admins must recreate equivalent squads and mappings.
