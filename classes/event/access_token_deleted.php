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
 * local_oauth2 access token deleted event.
 *
 * @package local_oauth2
 * @author Lai Wei <lai.wei@enovation.ie>
 * @author Dorel Manolescu <dorel.manolescu@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2025 Enovation Solutions
 */

namespace local_oauth2\event;

use context_system;
use core\event\base;

/**
 * The access_token_deleted event class.
 */
class access_token_deleted extends base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->context = context_system::instance();
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_oauth2_access_token';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_access_token_deleted', 'local_oauth2');
    }

    /**
     * Return description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $expiry = $this->data['other']['expiry'];
        return "Access token ID " . $this->objectid . " was deleted because it expired on " . date('r', $expiry);
    }
}
