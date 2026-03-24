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

use local_oauth2\controller\authorize_controller as oidc_authorize_controller;
use local_oauth2\form\authorize_form;
use local_oauth2\response_type\id_token as oidc_id_token_response;
use OAuth2\Autoloader;
use OAuth2\GrantType\RefreshToken;
use OAuth2\GrantType\UserCredentials;
use OAuth2\OpenID\GrantType\AuthorizationCode as oidc_authorization_code_grant;
use OAuth2\OpenID\ResponseType\AuthorizationCode as oidc_authorization_code_response;
use OAuth2\OpenID\ResponseType\CodeIdToken as oidc_code_id_token_response;
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
        require_once(__DIR__ . '/oidc_jwt.php');
        require_once(__DIR__ . '/response_type/id_token.php');
        require_once(__DIR__ . '/controller/authorize_controller.php');

        $storage = new moodle_oauth_storage([]);

        // Set access token lifetime.
        $accesstokenlifetime = get_config('local_oauth2', 'access_token_lifetime');
        if (!$accesstokenlifetime) {
            $accesstokenlifetime = HOURSECS;
        }

        $idtokenresponse = new oidc_id_token_response($storage, $storage, [
            'issuer' => self::get_issuer(),
            'id_lifetime' => intval($accesstokenlifetime),
        ]);
        $coderesponse = new oidc_authorization_code_response($storage);

        $responseTypes = [
            'code' => $coderesponse,
            'id_token' => $idtokenresponse,
            'code id_token' => new oidc_code_id_token_response($coderesponse, $idtokenresponse),
        ];

        // Pass a storage object or array of storage objects to the OAuth2 server class.
        // Enable OpenID Connect mode to use the AuthorizeController that supports PKCE.
        $server = new Server($storage, [
            'use_openid_connect' => true,
            'issuer' => self::get_issuer(),
        ], [], $responseTypes);
        $server->setConfig('enforce_state', false);
        $server->setAuthorizeController(new oidc_authorize_controller(
            $storage,
            $responseTypes,
            [
                'allow_implicit' => false,
                'enforce_state' => false,
                'require_exact_redirect_uri' => true,
                'enforce_pkce' => false,
            ],
            $server->getScopeUtil(),
            $storage
        ));

        $server->setConfig('access_lifetime', intval($accesstokenlifetime));
        $server->setConfig('id_lifetime', intval($accesstokenlifetime));

        // Set refresh token lifetime.
        $refreshtokenlifetime = get_config('local_oauth2', 'refresh_token_lifetime');
        if (!$refreshtokenlifetime) {
            $refreshtokenlifetime = WEEKSECS;
        }
        $server->setConfig('refresh_token_lifetime', intval($refreshtokenlifetime));

        // Add the "Authorization Code" grant type.
        $server->addGrantType(new oidc_authorization_code_grant($storage));

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
     * Get the public issuer URL for this provider.
     *
     * @return string
     */
    public static function get_issuer(): string {
        global $CFG;

        $issuer = trim((string) get_config('local_oauth2', 'issuer'));
        if ($issuer === '') {
            $issuer = $CFG->wwwroot;
        }

        return rtrim($issuer, '/');
    }

    /**
     * Build an endpoint URL for this plugin.
     *
     * @param string $path
     * @return string
     */
    public static function get_endpoint_url(string $path): string {
        return self::get_issuer() . '/local/oauth2/' . ltrim($path, '/');
    }

    /**
     * Get a signing key record, preferring a client-specific key and falling back to the default key.
     *
     * @param string|null $clientid
     * @return stdClass|null
     */
    public static function get_signing_key_record(?string $clientid = null): ?stdClass {
        global $DB;

        if (!empty($clientid)) {
            $record = $DB->get_record('local_oauth2_public_key', ['client_id' => $clientid]);
            if ($record) {
                return $record;
            }
        }

        $record = $DB->get_record('local_oauth2_public_key', ['client_id' => '']);
        return $record ?: null;
    }

    /**
     * Get all public signing key records that may be used to sign ID tokens.
     *
     * @return array
     */
    public static function get_all_signing_key_records(): array {
        global $DB;

        return $DB->get_records('local_oauth2_public_key', null, 'id ASC');
    }

    /**
     * Create a deterministic key identifier from a PEM encoded public key.
     *
     * @param string $publickey
     * @return string
     */
    public static function get_key_id_from_public_key(string $publickey): string {
        return self::base64url_encode(hash('sha256', $publickey, true));
    }

    /**
     * Convert a PEM encoded RSA public key into a JWK.
     *
     * @param string $publickey
     * @param string $algorithm
     * @param string|null $kid
     * @return array
     */
    public static function convert_public_key_to_jwk(string $publickey, string $algorithm = 'RS256', ?string $kid = null): array {
        $resource = openssl_pkey_get_public($publickey);
        if ($resource === false) {
            throw new \RuntimeException('Invalid RSA public key');
        }

        $details = openssl_pkey_get_details($resource);
        if ($details === false || empty($details['rsa']['n']) || empty($details['rsa']['e'])) {
            throw new \RuntimeException('Unable to read RSA public key details');
        }

        $jwk = [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => $algorithm,
            'n' => self::base64url_encode($details['rsa']['n']),
            'e' => self::base64url_encode($details['rsa']['e']),
        ];

        if (!empty($kid)) {
            $jwk['kid'] = $kid;
        }

        return $jwk;
    }

    /**
     * Get the JWK set for all configured signing keys.
     *
     * @return array
     */
    public static function get_jwks(): array {
        $keys = [];
        $seen = [];

        foreach (self::get_all_signing_key_records() as $record) {
            $kid = self::get_key_id_from_public_key($record->public_key);
            if (isset($seen[$kid])) {
                continue;
            }

            $seen[$kid] = true;
            $keys[] = self::convert_public_key_to_jwk(
                $record->public_key,
                $record->encryption_algorithm ?: 'RS256',
                $kid
            );
        }

        return ['keys' => $keys];
    }

    /**
     * Build OIDC discovery metadata for this provider.
     *
     * @return array
     */
    public static function get_openid_configuration(): array {
        global $DB;

        $scopes = $DB->get_fieldset('local_oauth2_scope', 'scope');
        if (empty($scopes)) {
            $scopes = ['openid', 'profile', 'email'];
        }

        $claims = [
            'sub',
            'iss',
            'aud',
            'exp',
            'iat',
            'auth_time',
            'nonce',
            'email',
            'email_verified',
            'name',
            'given_name',
            'family_name',
            'middle_name',
            'nickname',
            'preferred_username',
            'profile',
            'picture',
            'website',
            'zoneinfo',
            'locale',
            'updated_at',
            'address',
            'phone_number',
            'phone_number_verified',
        ];

        return [
            'issuer' => self::get_issuer(),
            'authorization_endpoint' => self::get_endpoint_url('login.php'),
            'token_endpoint' => self::get_endpoint_url('token.php'),
            'userinfo_endpoint' => self::get_endpoint_url('userinfo.php'),
            'jwks_uri' => self::get_endpoint_url('jwks.php'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'password'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post', 'none'],
            'scopes_supported' => array_values($scopes),
            'claims_supported' => $claims,
            'code_challenge_methods_supported' => ['plain', 'S256'],
            'claims_parameter_supported' => false,
            'request_parameter_supported' => false,
            'request_uri_parameter_supported' => false,
        ];
    }

    /**
     * Base64url encode binary data.
     *
     * @param string $value
     * @return string
     */
    public static function base64url_encode(string $value): string {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
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
