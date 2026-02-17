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

namespace local_oauth2\privacy;

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for local_oauth2 implements metadata provider.
 *
 * @package local_oauth2
 * @author Lai Wei <lai.wei@enovation.ie>
 * @author Dorel Manolescu <dorel.manolescu@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2025 Enovation Solutions
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string[] plugin tables that contain user data. */
    const TABLES = [
        'local_oauth2_user_auth_scope',
        'local_oauth2_access_token',
        'local_oauth2_authorization_code',
        'local_oauth2_refresh_token',
    ];

    /**
     *  Provides metadata that is stored about a user in the local_oauth2 plugin.
     *
     * @param collection $collection A collection of metadata items to be added to.
     * @return  collection Returns the collection of metadata.
     */
    public static function get_metadata(collection $collection): collection {
        // Add metadata for the local_oauth2_user_auth_scope table.
        $collection->add_database_table(
            'local_oauth2_user_auth_scope',
            [
                'user_id' => 'privacy:metadata:local_oauth2_user_auth_scope:user_id',
                'client_id' => 'privacy:metadata:local_oauth2_user_auth_scope:client_id',
                'scope' => 'privacy:metadata:local_oauth2_user_auth_scope:scope',
            ],
            'privacy:metadata:local_oauth2_user_auth_scope'
        );

        // Add metadata for the local_oauth2_access_token table.
        $collection->add_database_table(
            'local_oauth2_access_token',
            [
                'user_id' => 'privacy:metadata:local_oauth2_access_token:user_id',
                'client_id' => 'privacy:metadata:local_oauth2_access_token:client_id',
                'scope' => 'privacy:metadata:local_oauth2_access_token:scope',
                'access_token' => 'privacy:metadata:local_oauth2_access_token:access_token',
                'expires' => 'privacy:metadata:local_oauth2_access_token:expires',
            ],
            'privacy:metadata:local_oauth2_access_token'
        );

        // Add metadata for the local_oauth2_authorization_code table.
        $collection->add_database_table(
            'local_oauth2_authorization_code',
            [
                'user_id' => 'privacy:metadata:local_oauth2_authorization_code:user_id',
                'authorization_code' => 'privacy:metadata:local_oauth2_authorization_code:authorization_code',
                'client_id' => 'privacy:metadata:local_oauth2_authorization_code:client_id',
                'redirect_uri' => 'privacy:metadata:local_oauth2_authorization_code:redirect_uri',
                'expires' => 'privacy:metadata:local_oauth2_authorization_code:expires',
                'scope' => 'privacy:metadata:local_oauth2_authorization_code:scope',
                'id_token' => 'privacy:metadata:local_oauth2_authorization_code:id_token',
            ],
            'privacy:metadata:local_oauth2_authorization_code'
        );

        // Add metadata for the local_oauth2_refresh_token table.
        $collection->add_database_table(
            'local_oauth2_refresh_token',
            [
                'user_id' => 'privacy:metadata:local_oauth2_refresh_token:user_id',
                'refresh_token' => 'privacy:metadata:local_oauth2_refresh_token:refresh_token',
                'client_id' => 'privacy:metadata:local_oauth2_refresh_token:client_id',
                'expires' => 'privacy:metadata:local_oauth2_refresh_token:expires',
                'scope' => 'privacy:metadata:local_oauth2_refresh_token:scope',
            ],
            'privacy:metadata:local_oauth2_refresh_token'
        );

        return $collection;
    }

    /**
     * Returns the contexts that are relevant to the user.
     *
     * @param int $userid The user ID.
     * @return contextlist A list of contexts relevant to the user.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        foreach (self::TABLES as $table) {
            $sql = "SELECT ctx.id
                     FROM {{$table}} t
                     JOIN {context} ctx ON t.user_id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                    WHERE t.user_id = :userid";

            $params = [
                'contextlevel' => CONTEXT_USER,
                'userid' => $userid,
            ];

            $contextlist->add_from_sql($sql, $params);
        }

        return $contextlist;
    }

    /**
     * Returns the users in the context.
     *
     * @param userlist $userlist The user list to be populated.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof context_system) {
            return;
        }

        foreach (static::TABLES as $table) {
            $userlist->add_from_sql('user_id', "SELECT DISTINCT user_id FROM {$table}", []);
        }
    }

    /**
     * Exports user data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     * @return void
     */
    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contextlist) {
        if (empty($contextlist)) {
            return;
        }

        foreach ($contextlist as $context) {
            if ($context->contextlevel == CONTEXT_USER) {
                // Export user data for the specified user in the specified contexts.
                self::export_local_oauth2_userdata($context);
            }
        }
    }

    /**
     * Deletes user data for the specified user in the specified contexts.
     *
     * @param approved_userlist $userlist The approved user list to delete information for.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            foreach (static::TABLES as $table) {
                foreach ($userids as $userid) {
                    $DB->delete_records($table, ['user_id' => $userid]);
                }
            }
        }
    }

    /**
     * Deletes all data for all users in the specified context.
     *
     * @param context $context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if ($context->contextlevel == CONTEXT_SYSTEM) {
            foreach (static::TABLES as $table) {
                $DB->delete_records($table);
            }
        }
    }

    /**
     * Deletes all data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to delete information for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();
        $context = context_system::instance();
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            foreach (static::TABLES as $table) {
                $DB->delete_records($table, ['user_id' => $user->id]);
            }
        }
    }

    /**
     * Exports user data for the specified user in the specified contexts.
     *
     * @param context $context The context to export information for.
     * @return void
     */
    public static function export_local_oauth2_userdata(\context $context) {
        global $DB, $USER;
        if (!$context instanceof \context_user) {
            return;
        }

        $subcontext[] = get_string('pluginname', 'local_oauth2');

        $data = [];
        $usertokendata = [];
        $usercodedata = [];
        $userrefreshtokendata = [];
        $notexportedstr = get_string('privacy:request:notexportedsecurity', 'core_external');

        foreach (self::TABLES as $table) {
            $sql = "SELECT t.*
                      FROM {{$table}} t
                     WHERE t.user_id = :userid";
            $params = [
                'userid' => $USER->id,
            ];
            $records = $DB->get_records_sql($sql, $params);

            foreach ($records as $record) {
                $record->client_id = $notexportedstr;
                if ($table == 'local_oauth2_access_token') {
                    $record->access_token = $notexportedstr;
                    $record->expires = transform::datetime($record->expires);
                    $usertokendata[] = $record;
                } else if ($table == 'local_oauth2_authorization_code') {
                    $record->authorization_code = $notexportedstr;
                    $record->expires = transform::datetime($record->expires);
                    $usercodedata[] = $record;
                } else if ($table == 'local_oauth2_refresh_token') {
                    $record->refresh_token = $notexportedstr;
                    $record->expires = transform::datetime($record->expires);
                    $userrefreshtokendata[] = $record;
                } else {
                    $data[] = $record;
                }
            }
        }

        if (!empty($data) || !empty($usertokendata) || !empty($usercodedata) || !empty($userrefreshtokendata)) {
            \core_privacy\local\request\writer::with_context($context)
                ->export_data($subcontext, (object)[
                    'userauthscope' => $data,
                    'userauthtoken' => $usertokendata,
                    'userauthcode' => $usercodedata,
                    'userrefreshtoken' => $userrefreshtokendata,
                ]);
        }
    }
}
