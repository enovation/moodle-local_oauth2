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
 * Utility functions.
 *
 * @package local_oauth2
 * @author Lai Wei <lai.wei@enovation.ie>
 * @author Dorel Manolescu <dorel.manolescu@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2025 Enovation Solutions
 */

namespace local_oauth2;

use local_oauth2\form\authorize_form;
use OAuth2\Autoloader;
use OAuth2\GrantType\AuthorizationCode;
use OAuth2\GrantType\RefreshToken;
use OAuth2\GrantType\UserCredentials;
use OAuth2\Server;
use stdClass;

/**
 * Utility functions.
 */
class utils {
    /**
     * Generate a secret.
     *
     * @return string
     */
    public static function generate_secret(): string {
        // Get a bunch of random characters from the OS.
        $fp = fopen('/dev/urandom', 'rb');
        $entropy = fread($fp, 32);
        fclose($fp);

        // Takes our binary entropy, and concatenates a string which represents the current time to the microsecond.
        $entropy .= uniqid(mt_rand(), true);

        // Hash the binary entropy.
        $hash = hash('sha512', $entropy);

        // Chop and send the first 80 characters back to the client.
        return substr($hash, 0, 48);
    }

    /**
     * Get the OAuth server.
     *
     * @return Server
     */
    public static function get_oauth_server(): Server {
        global $CFG;

        // Autoload the required files.
        require_once($CFG->dirroot . '/local/oauth2/vendor/bshaffer/oauth2-server-php/src/OAuth2/Autoloader.php');
        Autoloader::register();

        $storage = new moodle_oauth_storage([]);

        // Pass a storage object or array of storage objects to the OAuth2 server class.
        // Enable OpenID Connect mode to use the AuthorizeController that supports PKCE.
        $server = new Server($storage, [
            'use_openid_connect' => true,
            'issuer' => $CFG->wwwroot,
        ]);
        $server->setConfig('enforce_state', false);

        // Set access token lifetime.
        $accesstokenlifetime = get_config('local_oauth2', 'access_token_lifetime');
        if (!$accesstokenlifetime) {
            $accesstokenlifetime = HOURSECS;
        }
        $server->setConfig('access_lifetime', intval($accesstokenlifetime));

        // Set refresh token lifetime.
        $refreshtokenlifetime = get_config('local_oauth2', 'refresh_token_lifetime');
        if (!$refreshtokenlifetime) {
            $refreshtokenlifetime = WEEKSECS;
        }
        $server->setConfig('refresh_token_lifetime', intval($refreshtokenlifetime));

        // Add the "Authorization Code" grant type.
        $server->addGrantType(new AuthorizationCode($storage));

        // Add the "Password" grant type (Resource Owner Password Credentials).
        $server->addGrantType(new UserCredentials($storage));

        // Add the "Refresh Token" grant type.
        $server->addGrantType(new RefreshToken($storage, [
            'always_issue_new_refresh_token' => true,
            'unset_refresh_token_after_use' => true,
        ]));

        return $server;
    }

    /**
     * Get authorization from form.
     *
     * @param string $url The URL.
     * @param string $clientid The client ID.
     * @param string $scope The scope.
     * @param array $customdata Additional data to pass to the form.
     * @return bool
     */
    public static function get_authorization_from_form($url, $clientid, $scope = false, $customdata = []): bool {
        global $OUTPUT, $USER;

        if (static::is_scope_authorized_by_user($USER->id, $clientid, $scope)) {
            return true;
        }

        $form = new authorize_form($url, $customdata);
        if ($form->is_cancelled()) {
            return false;
        }

        if (($form->get_data()) && confirm_sesskey()) {
            static::authorize_user_scope($USER->id, $clientid, $scope);
            return true;
        }

        echo $OUTPUT->header();
        $form->display();
        echo $OUTPUT->footer();

        die();
    }

    /**
     * Check if a scope is authorized by a user.
     *
     * @param int $userid The user ID.
     * @param string $clientid The client ID.
     * @param string $scope The scope.
     * @return bool
     */
    public static function is_scope_authorized_by_user($userid, $clientid, $scope = 'login'): bool {
        global $DB;

        return $DB->record_exists(
            'local_oauth2_user_auth_scope',
            ['client_id' => $clientid, 'scope' => $scope, 'user_id' => $userid]
        );
    }

    /**
     * Authorize a user scope.
     *
     * @param int $userid The user ID.
     * @param string $clientid The client ID.
     * @param string $scope The scope.
     */
    public static function authorize_user_scope($userid, $clientid, $scope = 'login'): void {
        global $DB;

        $record = new stdClass();
        $record->client_id = $clientid;
        $record->scope = $scope;
        $record->user_id = $userid;

        $DB->insert_record('local_oauth2_user_auth_scope', $record);
    }

    /**
     * Check if local_copilot plugin is installed.
     *
     * @return bool
     */
    public static function is_local_copilot_installed() {
        global $DB;

        $fileexist = file_exists(__DIR__ . '/../../copilot/version.php');
        $pluginversion = $DB->get_field('config_plugins', 'value', ['plugin' => 'local_copilot', 'name' => 'version']);

        return $fileexist && $pluginversion;
    }

    /**
     * Check if PKCE is required for a given client.
     *
     * @param string $clientid The client ID.
     * @return bool True if PKCE is required.
     */
    public static function is_pkce_required($clientid): bool {
        global $DB;

        $client = $DB->get_record('local_oauth2_client', ['client_id' => $clientid]);
        if ($client && !empty($client->require_pkce)) {
            return true;
        }

        return false;
    }
}
