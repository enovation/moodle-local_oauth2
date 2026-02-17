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
 * Plugin configuration.
 *
 * @package local_oauth2
 * @author Pau Ferrer Ocaña <pferre22@xtec.cat>
 * @author Lai Wei <lai.wei@enovation.ie>
 * @author Dorel Manolescu <dorel.manolescu@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2025 Enovation Solutions
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Add a section for the plugin configurations in the "Local plugins" section.
    $ADMIN->add('server', new admin_category('local_oauth2', get_string('pluginname', 'local_oauth2')));

    // Add OAuth provider settings to the "Server" section.
    $ADMIN->add(
        'local_oauth2',
        new admin_externalpage(
            'local_oauth2_manage_oauth_clients',
            get_string('manage_oauth_clients', 'local_oauth2'),
            new moodle_url('/local/oauth2/manage_oauth_clients.php'),
            'local/oauth2:manage_oauth_clients'
        )
    );

    // Add plugin configuration page.
    $settings = new admin_settingpage('local_oauth2_token_lifetime', get_string('settings_token_settings', 'local_oauth2'));
    $ADMIN->add('local_oauth2', $settings);

    // Access token timeout period.
    $settings->add(
        new admin_setting_configduration(
            'local_oauth2/access_token_lifetime',
            get_string('settings_access_token_lifetime', 'local_oauth2'),
            get_string('settings_access_token_lifetime_desc', 'local_oauth2'),
            HOURSECS,
            HOURSECS
        )
    );

    // Refresh token timeout period.
    $settings->add(
        new admin_setting_configduration(
            'local_oauth2/refresh_token_lifetime',
            get_string('settings_refresh_token_lifetime', 'local_oauth2'),
            get_string('settings_refresh_token_lifetime_desc', 'local_oauth2'),
            WEEKSECS,
            WEEKSECS
        )
    );
}
