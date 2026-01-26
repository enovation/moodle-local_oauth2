<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade script for local_oauth2 plugin.
 *
 * @package local_oauth2
 * @author Lai Wei <lai.wei@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2025 Enovation Solutions
 */

/**
 * Upgrade the local_oauth2 plugin.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool
 */
function xmldb_local_oauth2_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024100702) {
        $table = new xmldb_table('local_oauth2_authorization_code');

        // Add code_challenge field.
        $field = new xmldb_field('code_challenge', XMLDB_TYPE_CHAR, '128', null, null, null, null, 'id_token');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add code_challenge_method field.
        $field = new xmldb_field('code_challenge_method', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'code_challenge');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024100702, 'local', 'oauth2');
    }

    if ($oldversion < 2024100703) {
        $table = new xmldb_table('local_oauth2_client');

        // Add require_pkce field.
        $field = new xmldb_field('require_pkce', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'scope');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2024100703, 'local', 'oauth2');
    }

    if ($oldversion < 2024100704) {
        // Add default OpenID Connect scopes.
        $defaultscopes = [
            ['scope' => 'openid', 'is_default' => 1],
            ['scope' => 'profile', 'is_default' => 0],
            ['scope' => 'email', 'is_default' => 0],
            ['scope' => 'address', 'is_default' => 0],
            ['scope' => 'phone', 'is_default' => 0],
        ];

        foreach ($defaultscopes as $scopedata) {
            if (!$DB->record_exists('local_oauth2_scope', ['scope' => $scopedata['scope']])) {
                $record = (object) $scopedata;
                $DB->insert_record('local_oauth2_scope', $record);
            }
        }

        upgrade_plugin_savepoint(true, 2024100704, 'local', 'oauth2');
    }

    if ($oldversion < 2024100705) {
        // Change public_key and private_key columns from CHAR to TEXT to accommodate RSA keys.
        $table = new xmldb_table('local_oauth2_public_key');

        // Change public_key from CHAR(1333) to TEXT.
        $field = new xmldb_field('public_key', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'client_id');
        $dbman->change_field_type($table, $field);

        // Change private_key from CHAR(1333) to TEXT.
        $field = new xmldb_field('private_key', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null, 'public_key');
        $dbman->change_field_type($table, $field);

        // Generate RSA key pair for OpenID Connect ID token signing.
        // Use empty string for client_id to represent default keys for all clients.
        if (!$DB->record_exists('local_oauth2_public_key', ['client_id' => ''])) {
            $config = [
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ];

            // Generate RSA key pair.
            $res = openssl_pkey_new($config);
            if ($res !== false) {
                // Extract private key.
                openssl_pkey_export($res, $privatekey);

                // Extract public key.
                $publickey = openssl_pkey_get_details($res);
                $publickey = $publickey['key'];

                // Store keys in database with empty client_id (default keys for all clients).
                $record = new stdClass();
                $record->client_id = '';
                $record->public_key = $publickey;
                $record->private_key = $privatekey;
                $record->encryption_algorithm = 'RS256';

                $DB->insert_record('local_oauth2_public_key', $record);
            }
        }

        upgrade_plugin_savepoint(true, 2024100705, 'local', 'oauth2');
    }

    return true;
}
