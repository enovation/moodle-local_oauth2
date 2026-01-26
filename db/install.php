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
 * Post installation script for local_oauth2 plugin.
 *
 * @package    local_oauth2
 * @author     Lai Wei <lai.wei@enovation.ie>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2026 Enovation Solutions
 */

/**
 * Post installation hook.
 *
 * This function is called after the plugin tables have been created.
 * It initializes default OpenID Connect scopes.
 *
 * @return bool
 */
function xmldb_local_oauth2_install() {
    global $DB;

    // Define default OpenID Connect scopes.
    $defaultscopes = [
        ['scope' => 'openid', 'is_default' => 1],
        ['scope' => 'profile', 'is_default' => 0],
        ['scope' => 'email', 'is_default' => 0],
        ['scope' => 'address', 'is_default' => 0],
        ['scope' => 'phone', 'is_default' => 0],
    ];

    // Insert default scopes if they don't already exist.
    foreach ($defaultscopes as $scopedata) {
        if (!$DB->record_exists('local_oauth2_scope', ['scope' => $scopedata['scope']])) {
            $record = (object) $scopedata;
            $DB->insert_record('local_oauth2_scope', $record);
        }
    }

    // Generate RSA key pair for OpenID Connect ID token signing.
    // Use empty string for client_id to represent default keys for all clients.
    if (!$DB->record_exists('local_oauth2_public_key', ['client_id' => ''])) {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // Generate RSA key pair.
        $res = openssl_pkey_new($config);
        if ($res === false) {
            debugging('Failed to generate RSA key pair: ' . openssl_error_string(), DEBUG_DEVELOPER);
            return true;
        }

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

    return true;
}
