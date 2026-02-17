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
 * Scheduled task to clean up expired auth codes and tokens.
 *
 * @package local_oauth2
 * @author Pau Ferrer Ocaña <pferre22@xtec.cat>
 * @author Lai Wei <lai.wei@enovation.ie>
 * @author Dorel Manolescu <dorel.manolescu@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2025 Enovation Solutions
 */

namespace local_oauth2\task;

use core\task\scheduled_task;
use local_oauth2\event\access_token_deleted;

/**
 * Scheduled task to clean up expired auth codes and tokens.
 */
class cleanup extends scheduled_task {
    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cleanup', 'local_oauth2');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        mtrace("Deleting expired auth codes and tokens...");
        $time = time();

        // Delete expired auth codes.
        $expiredauthcodescount = $DB->count_records_select(
            'local_oauth2_authorization_code',
            'expires < :time',
            ['time' => $time]
        );
        if ($expiredauthcodescount > 0) {
            $DB->delete_records_select('local_oauth2_authorization_code', 'expires < :time', ['time' => $time]);
            mtrace("Deleted " . $expiredauthcodescount . " expired auth codes.");
        } else {
            mtrace("No expired auth codes found.");
        }

        // Delete expired access tokens.
        $accesstokenrecordset = $DB->get_recordset_select(
            'local_oauth2_access_token',
            'expires < :time',
            ['time' => $time],
            '',
            'id, expires'
        );
        $accesstokensdeleted = 0;
        foreach ($accesstokenrecordset as $accesstoken) {
            $DB->delete_records('local_oauth2_access_token', ['id' => $accesstoken->id]);
            $tokendeletedeventparams = [
                'objectid' => $accesstoken->id,
                'other' => [
                    'expiry' => $accesstoken->expires,
                ],
            ];
            $event = access_token_deleted::create($tokendeletedeventparams);
            $event->trigger();

            $accesstokensdeleted++;
        }
        $accesstokenrecordset->close();

        if ($accesstokensdeleted) {
            mtrace("Deleted " . $accesstokensdeleted . " expired access tokens.");
        } else {
            mtrace("No expired access tokens found.");
        }

        // Delete expired refresh tokens.
        $expiredrefreshtokenscount = $DB->count_records_select(
            'local_oauth2_refresh_token',
            'expires < :time',
            ['time' => $time]
        );
        if ($expiredrefreshtokenscount > 0) {
            $DB->delete_records_select('local_oauth2_refresh_token', 'expires < :time', ['time' => $time]);
            mtrace("Deleted " . $expiredrefreshtokenscount . " expired refresh tokens.");
        } else {
            mtrace("No expired refresh tokens found.");
        }
    }
}
