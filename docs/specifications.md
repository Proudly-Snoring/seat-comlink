## Core features

* SeAT admins define a map of SeAT "Squads" to discord "roles".
    * The roles may differ on each discord server, so there is one mapping per discord server.
* Users link their discord account with their SeAT account.
    * A command to import data from a preinstalled [seat-discord-connector](https://github.com/warlof/seat-discord-connector) is also provided.
* The plugin regularly enforce registered users' nickname and roles:
    * It enforce the user's nickname based on a format defined in configuration (ex: `[CORP] - CHARACTER_NAME`).
    * It adds or removes the managed discord roles to each registered user (when needed).
    * It removes the managed discord roles from any user that is not registered on SeAT.
    * Only the discord roles defined in the SeAT are managed. Other roles are untouched.

## How it works

* The plugin works as a discord bot invited to one or more discord servers.
* It maps users' discord ID to their SeAT account and SeAT Squads to discord roles.
* A regular time interval (ex: every 5 minutes), it lists users and their roles on each discord server. For each discord user:
    * If the user ID is known, it synchronizes the managed roles the user should have based on their squads.
    * If the user ID is unknown, it removes all the managed roles.
    * If the user ID is known, it also enforces their nickname.
* Users link their discord account with their SeAT account via the OAuth protocol.
    * In the SeAT, they click on a "link my discord" button. This triggers the OAuth flow.
    * After the OAuth flow is complete, the SeAT gets and records the user's discord account ID.
      This is what is used to recognize the user on discord.

## Views

* Connector/Identity
    * Displays a discord card with:
        * The "Discord" title with discord icon,
        * A "status" badge ("Linked" green if  user's ID is known / "Unknown" red else),
        * The connected user's username and ID on discord (if already known),
        * The "link my discord" button if user's ID is not already known.
        * A "unlink" button if user's ID is known.
    * May display other cards for other VOIP services (called "drivers") in the future.
    * Intended for all users (configurable from the standard SeAT roles management).
* Connector/Discord Mapping
    * Displays a table of roles mapping with the following columns:
        * Discord server name,
        * Squad name,
        * Role name,
        * An "Edit" button,
        * A "Delete" button.
    * The table must be filterable by discord server, squad name and role name.
    * An "Add mapping" button allows to add more rows.
    * Intended for admins only (configurable from the standard SeAT roles management).
* Connector/Settings
    * The plugin settings with:
        * The discord bot credentials (editable - secrets masked by default but with a button to reveal them),
        * The rate of the synchronization (1 minute to 1 hour),
        * The list of discord servers where the bot nis currently present with for each:
            * A "Enforce nickname" checkbox - If checked (default), the bot enforce the discord nicknames for this server, else it doesn't touch it.
            * A "Enforce roles" checkbox - If checked (default), the bot enforce the discord roles for this server, else it doesn't touch it.
            * The nickname format (if "Enforce nickname" is checked) - default is `[CORP_TICKER] MAIN_CHARACTER_NAME`,
            * A button for each one to force synchronization now.
    * Intended for admins only (configurable from the standard SeAT roles management).
* Connector/Logs
    * The plugin logs in table format, sorted from newer to older with pagination and the following columns:
        * Datetime of the log,
        * Log level (debug/info/warning/error),
        * The driver name (only "discord" for now),
        * The actual log message (plain text).
    * The table must be filterable by log level, driver name, and datetime range.
    * Intended for admins only (configurable from the standard SeAT roles management).
* Connector/Users
    * Displays a table of known discord users with the following columns:
        * The SeAT user ID,
        * The user's name (SeAT main character),
        * The discord ID,
        * The discord username,
        * The enforced nickname on discord,
        * The discord servers where the user is present.
    * Intended for admins only (configurable from the standard SeAT roles management).
Each view must have a corresponding entry in the SeAT Access Management configuration.

## Logs

* When an admin changes a mapping: `Added/edited/removed mapping SQUAD_NAME -> ROLE_NAME for discord server SERVER_NAME` (level info).
* When an admin changes the settings: `Updated discord settings` (level info).
* When a user links/unlinks : `Linked/Unlinked discord account DISCORD_ID with user USER_ID` (level info).
* When the bot is added/removed to/from a discord server: `Invited/Removed to/from server DISCORD_SERVER` (level info).
* When a nickname or role cannot be updated (usually due to a permission error): `Failed to update nickname/role for user SEAT_CHARACTER_NAME (discord id: DISCORD_ID) on server DISCORD_SERVER: REASON` (level warning).
* Any other error is logged with error level.

Each log also includes the event datetime and the user at the origin of the event as metadata.
* In case of seynchronization errors, the user is null.
