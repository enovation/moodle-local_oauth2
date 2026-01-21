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

    return true;
}
