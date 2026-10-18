<?php

/*
 * This file is part of seat-comlink.
 *
 * Copyright (C) 2026 Proudly-Snoring
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

return [
    // menu
    'menu' => 'Comlink',
    'menu_identity' => 'Identity',
    'menu_mappings' => 'Discord Mappings',
    'menu_settings' => 'Settings',
    'menu_logs' => 'Logs',
    'menu_users' => 'Users',

    // permissions
    'permission_identity_label' => 'Identity',
    'permission_identity_description' => 'Link / unlink one\'s own discord account.',
    'permission_mappings_label' => 'Discord Mappings',
    'permission_mappings_description' => 'Manage the squads to discord roles mappings.',
    'permission_settings_label' => 'Settings',
    'permission_settings_description' => 'Manage the bot credentials, sync rate and per-server options.',
    'permission_logs_label' => 'Logs',
    'permission_logs_description' => 'Read the plugin logs.',
    'permission_users_label' => 'Users',
    'permission_users_description' => 'List the linked users and their discord state.',

    // common
    'save' => 'Save',
    'cancel' => 'Cancel',
    'confirm' => 'Confirm',
    'reveal' => 'Show / hide',
    'copy' => 'Copy',
    'never' => 'never',
    'filter' => 'Filter',
    'reset' => 'Reset',
    'all' => 'All',
    'minutes' => '{1} :count minute|[2,*] :count minutes',

    // identity
    'status_linked' => 'Linked',
    'status_unknown' => 'Unknown',
    'discord_username' => 'Discord username',
    'discord_id' => 'Discord ID',
    'identity_help' => 'Link your discord account to get your roles and nickname on the discord servers. You must join the servers by yourself.',
    'link' => 'Link my discord',
    'unlink' => 'Unlink',
    'unlink_confirm' => 'Unlink the discord account :username? Your managed roles will be removed at the next synchronization.',
    'replace_title' => 'Discord account already linked',
    'replace_confirm' => 'The discord account :username is already linked to another SeAT user. If you confirm, the previous link will be deleted and the account will be linked to you.',
    'linked' => 'Discord account linked.',
    'unlinked' => 'Discord account unlinked. Your managed roles will be removed at the next synchronization.',
    'link_cancelled' => 'Discord authorization cancelled.',
    'link_invalid_state' => 'Invalid discord authorization, please try again.',
    'link_failed' => 'Cannot identify your discord account, please try again later.',
    'not_configured' => 'The discord bot is not configured yet. Contact an administrator.',

    // settings
    'settings_saved' => 'Settings saved.',
    'settings_general' => 'Discord bot',
    'procedure_title' => 'How to create the discord bot',
    'procedure_toggle' => 'Show / hide',
    'procedure_step_application' => 'Open the <a href=":url" target="_blank" rel="noopener">discord developer portal</a> and create a new application.',
    'procedure_step_oauth' => 'In <b>OAuth2</b>, copy the <b>Client ID</b> and the <b>Client Secret</b> below.',
    'procedure_step_redirect' => 'Still in <b>OAuth2</b>, add the redirect URL <code>:url</code>.',
    'procedure_step_token' => 'In <b>Bot</b>, reset and copy the <b>Token</b> below. You may disable <b>Public Bot</b>.',
    'procedure_step_intent' => 'Still in <b>Bot</b>, enable the <b>Server Members Intent</b> (required to list the members).',
    'procedure_step_save' => 'Save the settings.',
    'procedure_step_invite' => 'Click on <b>Invite bot</b> and add the bot to each discord server (<i>Manage Roles</i> and <i>Manage Nicknames</i> permissions).',
    'procedure_step_hierarchy' => 'On each discord server, move the bot\'s role <b>above</b> every role it must manage.',
    'client_id' => 'Client ID',
    'client_secret' => 'Client Secret',
    'bot_token' => 'Bot Token',
    'callback_url' => 'OAuth callback URL',
    'callback_url_help' => 'Register this URL as a redirect in the OAuth2 section of the discord application.',
    'invite_bot' => 'Invite bot',
    'invite_bot_disabled' => 'Save the client ID first.',
    'sync_rate' => 'Synchronization rate',
    'log_retention' => 'Logs retention (days)',
    'servers' => 'Discord servers',
    'no_servers' => 'The bot is not present on any discord server yet: invite it, then wait for the next synchronization (or use "Import roles now" in the mappings).',
    'last_synced' => 'last synchronization',
    'enforce_nickname' => 'Enforce nickname',
    'enforce_roles' => 'Enforce roles',
    'nickname_format' => 'Nickname format',
    'nickname_tokens' => 'Available tokens:',
    'preview' => 'Preview (for you)',
    'nickname_rules' => 'Discord naming rules: leading, trailing and repeated whitespaces and zero-width / non-rendering characters are removed, and nicknames longer than :max characters are truncated. Missing values are replaced by "-".',
    'sync_now' => 'Sync now',
    'sync_queued' => 'Synchronization of :server queued.',

    // mappings
    'server' => 'Discord server',
    'squad' => 'Squad',
    'role' => 'Role',
    'mapping' => 'Mapping',
    'add_mapping' => 'Add mapping',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'no_mappings' => 'No mapping.',
    'orphan' => 'orphan',
    'orphan_help' => 'This role has been deleted on discord: the mapping is ignored by the synchronization.',
    'roles_help' => 'Roles as known at the last synchronization. Roles the bot cannot manage are displayed but not selectable.',
    'import_roles' => 'Import roles now',
    'import_roles_done' => '{0} No discord server found: invite the bot first.|{1} Roles imported from :count server.|[2,*] Roles imported from :count servers.',
    'import_roles_failed' => 'Cannot import the roles from discord: :reason',
    'mapping_saved' => 'Mapping saved.',
    'mapping_removed' => 'Mapping removed.',
    'mapping_delete_confirm' => 'Delete this mapping?',
    'mapping_invalid_server' => 'Unknown discord server.',
    'mapping_invalid_role' => 'This role does not exist on the selected server.',
    'mapping_duplicate' => 'This mapping already exists.',

    // logs
    'date' => 'Date',
    'level' => 'Level',
    'driver' => 'Driver',
    'message' => 'Message',
    'from' => 'From',
    'to' => 'To',
    'no_logs' => 'No log.',

    // users
    'seat_user_id' => 'SeAT user ID',
    'user_name' => 'Name',
    'servers_nicknames' => 'Servers (nickname)',
    'no_server' => 'Not seen on any server',
    'no_users' => 'No linked user.',
    'users_search' => 'Name, discord username or ID',

    // synchronization
    'reason_owner' => 'the server owner\'s nickname cannot be changed by a bot',
    'reason_above_bot' => 'the member is ranked above the bot\'s highest role',

    // roles
    'role_reason_everyone' => 'The @everyone role cannot be assigned.',
    'role_reason_integration' => 'Managed by an integration (bot or subscription).',
    'role_reason_hierarchy' => 'Above the bot\'s highest role: move the bot\'s role up on discord.',
];
