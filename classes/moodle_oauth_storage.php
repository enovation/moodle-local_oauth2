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
 * Moodle OAuth storage class.
 *
 * @package local_oauth2
 * @author Lai Wei <lai.wei@enovation.ie>
 * @author Dorel Manolescu <dorel.manolescu@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2025 Enovation Solutions
 */

namespace local_oauth2;

use context_system;
use local_oauth2\event\access_token_created;
use moodle_exception;
use OAuth2\OpenID\Storage\UserClaimsInterface;
use OAuth2\OpenID\Storage\AuthorizationCodeInterface as OpenIDAuthorizationCodeInterface;
use OAuth2\Storage\AccessTokenInterface;
use OAuth2\Storage\AuthorizationCodeInterface;
use OAuth2\Storage\ClientCredentialsInterface;
use OAuth2\Storage\JwtBearerInterface;
use OAuth2\Storage\PublicKeyInterface;
use OAuth2\Storage\RefreshTokenInterface;
use OAuth2\Storage\ScopeInterface;
use OAuth2\Storage\UserCredentialsInterface;
use stdClass;

// phpcs:disable moodle.NamingConventions.ValidFunctionName.LowercaseMethod

/**
 * Class moodle_oauth_storage.
 */
class moodle_oauth_storage implements
    AuthorizationCodeInterface,
    AccessTokenInterface,
    ClientCredentialsInterface,
    UserCredentialsInterface,
    RefreshTokenInterface,
    JwtBearerInterface,
    ScopeInterface,
    PublicKeyInterface,
    UserClaimsInterface,
    OpenIDAuthorizationCodeInterface {

    /**
     * Valid OpenID Connect scopes.
     */
    const VALID_CLAIMS = 'profile email address phone';

    /**
     * OpenID Connect profile claim values mapped to Moodle user fields.
     */
    const PROFILE_CLAIM_VALUES = 'name family_name given_name middle_name nickname preferred_username profile picture website gender birthdate zoneinfo locale updated_at';

    /**
     * OpenID Connect email claim values mapped to Moodle user fields.
     */
    const EMAIL_CLAIM_VALUES = 'email email_verified';

    /**
     * OpenID Connect address claim values mapped to Moodle user fields.
     */
    const ADDRESS_CLAIM_VALUES = 'formatted street_address locality region postal_code country';

    /**
     * OpenID Connect phone claim values mapped to Moodle user fields.
     */
    const PHONE_CLAIM_VALUES = 'phone_number phone_number_verified';

    /**
     * @var array config
     */
    protected $config;

    /**
     * Constructor.
     *
     * @param array $connection
     * @param array $config
     */
    public function __construct(array $connection, array $config = []) {
        $this->config = $config;
    }

    /**
     * Make sure that the client credentials is valid.
     *
     * @param string $clientid
     * @param string|null $clientsecret
     * @return bool
     */
    public function checkClientCredentials($clientid, $clientsecret = null) {
        global $DB;

        $clientsecretfield = $DB->get_field('local_oauth2_client', 'client_secret', ['client_id' => $clientid]);

        // Handle public clients (empty secret).
        if (empty($clientsecretfield) && empty($clientsecret)) {
            return true;
        }

        return $clientsecretfield === $clientsecret;
    }

    /**
     * Determine if the client is a "public" client, and therefore does not require passing credentials for certain grant types.
     *
     * @param string $clientid
     * @return bool
     */
    public function isPublicClient($clientid) {
        global $DB;

        $client = $DB->get_record('local_oauth2_client', ['client_id' => $clientid]);
        if (!$client) {
            return false;
        }
        return empty($client->client_secret);
    }

    /**
     * Get client details corresponding client_id.
     *
     * @param string $clientid
     * @return array|bool
     */
    public function getClientDetails($clientid) {
        global $DB;

        if ($client = $DB->get_record('local_oauth2_client', ['client_id' => $clientid])) {
            unset($client->id);
            return (array)$client;
        } else {
            return false;
        }
    }

    /**
     * Create or update client details.
     *
     * @param string $clientid
     * @param string $clientsecret
     * @param string $redirecturi
     * @param string|null $scope
     * @return bool
     */
    public function setClientDetails($clientid, $clientsecret, $redirecturi, $scope = null) {
        global $DB;

        if ($client = $DB->get_record('local_oauth2_client', ['client_id' => $clientid])) {
            $client->client_secret = $clientsecret;
            $client->redirect_uri = $redirecturi;
            $client->scope = $scope;

            $DB->update_record('local_oauth2_client', $client);
        } else {
            $client = new stdClass();
            $client->client_id = $clientid;
            $client->client_secret = $clientsecret;
            $client->redirect_uri = $redirecturi;
            $client->scope = $scope;

            $DB->insert_record('local_oauth2_client', $client);
        }

        return true;
    }

    /**
     * Check restricted grant types of corresponding client identifier.
     *
     * @param string $clientid
     * @param string $granttype
     * @return bool
     */
    public function checkRestrictedGrantType($clientid, $granttype) {
        return true;
    }

    /**
     * Look up the supplied oauth_token from storage.
     *
     * @param string $oauthtoken
     * @return bool
     */
    public function getAccessToken($oauthtoken) {
        global $DB;

        if ($token = $DB->get_record('local_oauth2_access_token', ['access_token' => $oauthtoken])) {
            unset($token->id);
            return (array)$token;
        } else {
            return false;
        }
    }

    /**
     * Store the supplied access token values to storage.
     *
     * @param string $accesstoken
     * @param string $clientid
     * @param int $userid
     * @param int $expires
     * @param string|null $scope
     * @return bool
     */
    public function setAccessToken($accesstoken, $clientid, $userid, $expires, $scope = null) {
        global $DB;

        if ($token = $DB->get_record('local_oauth2_access_token', ['access_token' => $accesstoken])) {
            $token->client_id = $clientid;
            $token->user_id = $userid;
            $token->expires = $expires;
            $token->scope = $scope;

            $DB->update_record('local_oauth2_access_token', $token);

            $tokencreatedeventparams = [
                'objectid' => $token->id,
                'userid' => $userid,
                'other' => [
                    'clientid' => $clientid,
                    'scope' => $scope,
                    'accesstoken' => $accesstoken,
                    'expires' => $expires,
                ],
            ];
            $event = access_token_updated::create($tokencreatedeventparams);
            $event->trigger();
        } else {
            $token = new stdClass();
            $token->access_token = $accesstoken;
            $token->client_id = $clientid;
            $token->user_id = $userid;
            $token->expires = $expires;
            $token->scope = $scope;

            $token->id = $DB->insert_record('local_oauth2_access_token', $token);

            $tokencreatedeventparams = [
                'objectid' => $token->id,
                'userid' => $userid,
                'other' => [
                    'clientid' => $clientid,
                    'scope' => $scope,
                    'accesstoken' => $accesstoken,
                    'expires' => $expires,
                ],
            ];
            $event = access_token_created::create($tokencreatedeventparams);
            $event->trigger();
        }

        return true;
    }

    /**
     * Fetch authorization code data (probably the most common grant type).
     *
     * Retrieve the stored data for the given authorization code.
     *
     * Required for OAuth2::GRANT_TYPE_AUTH_CODE.
     *
     * @param string $code
     * @return array|false
     */
    public function getAuthorizationCode($code) {
        global $DB;

        if ($authcode = $DB->get_record('local_oauth2_authorization_code', ['authorization_code' => $code])) {
            unset($authcode->id);
            return (array)$authcode;
        } else {
            return false;
        }
    }

    /**
     * Take the provided authorization code values and store them somewhere.
     *
     * @param string $code
     * @param string $clientid
     * @param int $userid
     * @param string $redirecturi
     * @param int $expires
     * @param string|null $scope
     * @param string|null $idtoken
     * @param string|null $codechallenge
     * @param string|null $codechallengemethod
     * @return bool
     */
    public function setAuthorizationCode($code, $clientid, $userid, $redirecturi, $expires, $scope = null, $idtoken = null,
        $codechallenge = null, $codechallengemethod = null) {
        global $DB;

        if (func_num_args() > 6) {
            return call_user_func_array([$this, 'setAuthorizationCodeWithIdToken'], func_get_args());
        }

        if ($authcode = $DB->get_record('local_oauth2_authorization_code', ['authorization_code' => $code])) {
            $authcode->client_id = $clientid;
            $authcode->user_id = $userid;
            $authcode->redirect_uri = $redirecturi;
            $authcode->expires = $expires;
            $authcode->scope = $scope;
            $authcode->code_challenge = $codechallenge;
            $authcode->code_challenge_method = $codechallengemethod;

            $DB->update_record('local_oauth2_authorization_code', $authcode);
        } else {
            $authcode = new stdClass();
            $authcode->authorization_code = $code;
            $authcode->client_id = $clientid;
            $authcode->user_id = $userid;
            $authcode->redirect_uri = $redirecturi;
            $authcode->expires = $expires;
            $authcode->scope = $scope;
            $authcode->code_challenge = $codechallenge;
            $authcode->code_challenge_method = $codechallengemethod;

            $DB->insert_record('local_oauth2_authorization_code', $authcode);
        }

        return true;
    }

    /**
     * Set authorization code with ID token.
     *
     * @param string $code
     * @param string $clientid
     * @param int $userid
     * @param string $redirecturi
     * @param int $expires
     * @param string|null $scope
     * @param string|null $idtoken
     * @param string|null $codechallenge
     * @param string|null $codechallengemethod
     * @return bool
     */
    private function setAuthorizationCodeWithIdToken($code, $clientid, $userid, $redirecturi, $expires, $scope = null,
        $idtoken = null, $codechallenge = null, $codechallengemethod = null) {
        global $DB;

        if ($authcode = $DB->get_record('local_oauth2_authorization_code', ['authorization_code' => $code])) {
            $authcode->client_id = $clientid;
            $authcode->user_id = $userid;
            $authcode->redirect_uri = $redirecturi;
            $authcode->expires = $expires;
            $authcode->scope = $scope;
            $authcode->id_token = $idtoken;
            $authcode->code_challenge = $codechallenge;
            $authcode->code_challenge_method = $codechallengemethod;

            $DB->update_record('local_oauth2_authorization_code', $authcode);
        } else {
            $authcode = new stdClass();
            $authcode->authorization_code = $code;
            $authcode->client_id = $clientid;
            $authcode->user_id = $userid;
            $authcode->redirect_uri = $redirecturi;
            $authcode->expires = $expires;
            $authcode->scope = $scope;
            $authcode->id_token = $idtoken;
            $authcode->code_challenge = $codechallenge;
            $authcode->code_challenge_method = $codechallengemethod;

            $DB->insert_record('local_oauth2_authorization_code', $authcode);
        }

        return true;
    }

    /**
     * Once an Authorization Code is used, it must be expired.
     *
     * @param string $code
     */
    public function expireAuthorizationCode($code) {
        global $DB;

        $DB->delete_records('local_oauth2_authorization_code', ['authorization_code' => $code]);
    }

    /**
     * Grant access tokens for basic user credentials.
     *
     * Check the supplied username and password for validity.
     *
     * You can also use the $client_id param to do any checks required based on a client, if you need that.
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function checkUserCredentials($username, $password) {
        if ($user = $this->getUser($username)) {
            return $this->checkPassword($user, $password);
        }
        return false;
    }

    /**
     * Get user details.
     *
     * @param string $username
     * @return array|bool
     */
    public function getUserDetails($username) {
        return $this->getUser($username);
    }

    /**
     * Return claims about the provided user id.
     *
     * @param int $userid
     * @param string $claims
     * @return array
     */
    public function getUserClaims($userid, $claims) {
        global $DB;

        // Get user by ID (not username).
        $userrecord = $DB->get_record('user', ['id' => $userid]);
        if (!$userrecord) {
            return [];
        }

        $userdetails = (array)$userrecord;
        $userdetails['user_id'] = $userdetails['id'];

        $claims = explode(' ', $claims);
        $userclaims = [];

        $validclaims = explode(' ', self::VALID_CLAIMS);
        foreach ($validclaims as $validclaim) {
            if (in_array($validclaim, $claims, true)) {
                if ($validclaim === 'address') {
                    $userclaims['address'] = $this->getUserClaim($validclaim, $userdetails['address'] ?: $userdetails);
                } else {
                    $userclaims = array_merge($userclaims, $this->getUserClaim($validclaim, $userdetails));
                }
            }
        }

        return $userclaims;
    }

    /**
     * Get user claim values.
     *
     * Maps Moodle user fields to OpenID Connect claim values.
     *
     * @param string $claim The claim type (profile, email, address, phone)
     * @param array $userdetails Moodle user record as array
     * @return array Array of claim values
     */
    protected function getUserClaim($claim, $userdetails) {
        global $CFG;

        $userclaims = [];
        $claimvaluesstring = constant(sprintf('self::%s_CLAIM_VALUES', strtoupper($claim)));
        $claimvalues = explode(' ', $claimvaluesstring);

        foreach ($claimvalues as $claimvalue) {
            // Map OpenID Connect claims to Moodle user fields.
            switch ($claimvalue) {
                case 'name':
                    $userclaims[$claimvalue] = fullname((object)$userdetails);
                    break;
                case 'given_name':
                    $userclaims[$claimvalue] = $userdetails['firstname'] ?? null;
                    break;
                case 'family_name':
                    $userclaims[$claimvalue] = $userdetails['lastname'] ?? null;
                    break;
                case 'middle_name':
                    $userclaims[$claimvalue] = $userdetails['middlename'] ?? null;
                    break;
                case 'nickname':
                    $userclaims[$claimvalue] = $userdetails['alternatename'] ?? null;
                    break;
                case 'preferred_username':
                    $userclaims[$claimvalue] = $userdetails['username'] ?? null;
                    break;
                case 'profile':
                    if (!empty($userdetails['id'])) {
                        $userclaims[$claimvalue] = $CFG->wwwroot . '/user/profile.php?id=' . $userdetails['id'];
                    }
                    break;
                case 'picture':
                    if (!empty($userdetails['id'])) {
                        global $PAGE;
                        $userpicture = new \user_picture((object)$userdetails);
                        $userpicture->size = 1; // Large size.
                        $userclaims[$claimvalue] = $userpicture->get_url($PAGE)->out(false);
                    }
                    break;
                case 'email':
                    $userclaims[$claimvalue] = $userdetails['email'] ?? null;
                    break;
                case 'email_verified':
                    // Email is verified if emailstop is 0 (not bouncing).
                    $userclaims[$claimvalue] = isset($userdetails['emailstop']) ? ($userdetails['emailstop'] == 0) : false;
                    break;
                case 'gender':
                    // Moodle doesn't have a standard gender field, return null.
                    $userclaims[$claimvalue] = null;
                    break;
                case 'birthdate':
                    // Moodle doesn't have a standard birthdate field, return null.
                    $userclaims[$claimvalue] = null;
                    break;
                case 'zoneinfo':
                    $userclaims[$claimvalue] = $userdetails['timezone'] ?? null;
                    break;
                case 'locale':
                    $userclaims[$claimvalue] = $userdetails['lang'] ?? null;
                    break;
                case 'updated_at':
                    // Return timestamp of last modification.
                    $userclaims[$claimvalue] = isset($userdetails['timemodified']) ? (int)$userdetails['timemodified'] : null;
                    break;
                case 'website':
                    $userclaims[$claimvalue] = $userdetails['url'] ?? null;
                    break;
                case 'phone_number':
                    $userclaims[$claimvalue] = $userdetails['phone1'] ?? $userdetails['phone2'] ?? null;
                    break;
                case 'phone_number_verified':
                    // Moodle doesn't verify phone numbers, return false.
                    $userclaims[$claimvalue] = false;
                    break;
                case 'formatted':
                case 'street_address':
                case 'locality':
                case 'region':
                case 'postal_code':
                case 'country':
                    // Map address fields if available.
                    $userclaims[$claimvalue] = $userdetails['address'] ?? $userdetails['city'] ?? $userdetails['country'] ?? null;
                    if ($claimvalue === 'country') {
                        $userclaims[$claimvalue] = $userdetails['country'] ?? null;
                    }
                    break;
                default:
                    // For any other claims, try direct mapping.
                    $userclaims[$claimvalue] = $userdetails[$claimvalue] ?? null;
                    break;
            }
        }

        return $userclaims;
    }

    /**
     * Grant refresh access tokens.
     *
     * Retrieve the stored data for the given refresh token.
     *
     * @param string $refreshtoken
     * @return array|false
     */
    public function getRefreshToken($refreshtoken) {
        global $DB;

        if ($token = $DB->get_record('local_oauth2_refresh_token', ['refresh_token' => $refreshtoken])) {
            unset($token->id);
            return (array)$token;
        } else {
            return false;
        }
    }

    /**
     * Expire a used refresh token.
     *
     * This is not explicitly required in the spec, but is almost implied.
     * After granting a new refresh token, the old one is no longer useful and so should be forcibly expired in the data store
     * so it can't be used again.
     *
     * If storage fails for some reason, we're not currently checking for any sort of success/failure, so you should bail out of
     * the script and provide a descriptive fail message.
     *
     * @param string $refreshtoken
     * @return bool
     */
    public function unsetRefreshToken($refreshtoken) {
        global $DB;

        return $DB->delete_records('local_oauth2_refresh_token', ['refresh_token' => $refreshtoken]);
    }

    /**
     * Take the provided refresh token values and store them somewhere.
     *
     * This function should be the storage counterpart to getRefreshToken().
     *
     * If storage fails for some reason, we're not currently checking for any sort of success/failure,
     * so you should bail out of the script and provide a descriptive fail message.
     *
     * @param string $refreshtoken
     * @param string $clientid
     * @param int $userid
     * @param int $expires
     * @param string|null $scope
     * @return bool
     */
    public function setRefreshToken($refreshtoken, $clientid, $userid, $expires, $scope = null) {
        global $DB;

        $token = new stdClass();
        $token->refresh_token = $refreshtoken;
        $token->client_id = $clientid;
        $token->user_id = $userid;
        $token->expires = $expires;
        $token->scope = $scope;

        $DB->insert_record('local_oauth2_refresh_token', $token);

        return true;
    }

    /**
     * Check the password for a user.
     *
     * @param string $user
     * @param string $password
     * @return bool
     */
    protected function checkPassword($user, $password) {
        $user = (object)$user;
        return validate_internal_user_password($user, $password);
    }

    /**
     * Get user details.
     *
     * @param string $username
     * @return array|bool
     */
    public function getUser($username) {
        global $DB;

        if ($userrecord = $DB->get_record('user', ['username' => $username])) {
            $userrecord = (array)$userrecord;
            $userrecord['user_id'] = $userrecord['id'];

            return $userrecord;
        } else {
            return false;
        }
    }

    /**
     * Set user details.
     *
     * @param string $username
     * @param string $password
     * @param string $firstname
     * @param string $lastname
     * @return bool
     */
    public function setUser($username, $password, $firstname, $lastname) {
        global $DB;

        if ($user = $DB->get_record('user', ['username' => $username])) {
            if ($firstname) {
                $DB->set_field('user', 'firstname', $firstname, ['username' => $username]);
            }
            if ($lastname) {
                $DB->set_field('user', 'lastname', $lastname, ['username' => $username]);
            }
            update_internal_user_password($user, $password);
        } else {
            $user = create_user_record($username, $password);
            if ($user) {
                if ($firstname) {
                    $DB->set_field('user', 'firstname', $firstname, ['id' => $user->id]);
                }
                if ($lastname) {
                    $DB->set_field('user', 'lastname', $lastname, ['id' => $user->id]);
                }
            }
        }

        return true;
    }

    /**
     * Check if a scope exists.
     *
     * @param string $scope
     * @return bool|void
     */
    public function scopeExists($scope) {
        global $DB;

        $scope = explode(' ', $scope);
        if ($scope) {
            [$scopesql, $params] = $DB->get_in_or_equal($scope);
            $scopecount = $DB->count_records_select('local_oauth2_scope', "scope $scopesql", $params);
            return count($scope) === $scopecount;
        } else {
            false;
        }
    }

    /**
     * Get default scope.
     *
     * @param string|null $clientid
     * @return string|null
     */
    public function getDefaultScope($clientid = null) {
        global $DB;

        if ($scope = $DB->get_fieldset_select('local_oauth2_scope', 'scope', 'is_default = :is_default',
            ['is_default' => true])) {
            return implode(' ', $scope);
        }

        return null;
    }

    /**
     * Get the public key associated with a client_id.
     *
     * @param string $clientid
     * @param string $subject
     * @return false|mixed
     */
    public function getClientKey($clientid, $subject) {
        global $DB;

        return $DB->get_field('local_oauth2_jwt', 'public_key', ['client_id' => $clientid, 'subject' => $subject]);
    }

    /**
     * Get the scope associated with this client.
     *
     * @param string $clientid
     * @return false|mixed
     */
    public function getClientScope($clientid) {
        if (!$clientdetails = $this->getClientDetails($clientid)) {
            return false;
        }
        if (isset($clientdetails['scope'])) {
            return $clientdetails['scope'];
        }

        return null;
    }

    /**
     * Get a jti (JSON token identifier) by matching against the client_id, subject, audience and expiration.
     *
     * @param string $clientid
     * @param string $subject
     * @param string $audience
     * @param int $expiration
     * @param string $jti
     * @return mixed
     * @throws moodle_exception
     */
    public function getJti($clientid, $subject, $audience, $expiration, $jti) {
        throw new moodle_exception('not_implemented', 'local_oauth2');
    }

    /**
     * Store a used jti so that we can check against it to prevent replay attacks.
     *
     * @param string $clientid
     * @param string $subject
     * @param string $audience
     * @param int $expiration
     * @param string $jti
     * @return mixed
     * @throws moodle_exception
     */
    public function setJti($clientid, $subject, $audience, $expiration, $jti) {
        throw new moodle_exception('not_implemented', 'local_oauth2');
    }

    /**
     * Get the public key associated with a client_id.
     *
     * @param string|null $clientid
     * @return mixed
     */
    public function getPublicKey($clientid = null) {
        global $DB;

        // First try to get client-specific key, then fall back to default (empty string).
        if ($clientid) {
            $key = $DB->get_field('local_oauth2_public_key', 'public_key', ['client_id' => $clientid]);
            if ($key) {
                return $key;
            }
        }

        // Fall back to default key (empty client_id).
        return $DB->get_field('local_oauth2_public_key', 'public_key', ['client_id' => '']);
    }

    /**
     * Get the private key associated with a client_id.
     *
     * @param string|null $clientid
     * @return mixed
     */
    public function getPrivateKey($clientid = null) {
        global $DB;

        // First try to get client-specific key, then fall back to default (empty string).
        if ($clientid) {
            $key = $DB->get_field('local_oauth2_public_key', 'private_key', ['client_id' => $clientid]);
            if ($key) {
                return $key;
            }
        }

        // Fall back to default key (empty client_id).
        return $DB->get_field('local_oauth2_public_key', 'private_key', ['client_id' => '']);
    }

    /**
     * Get the encryption algorithm associated with a client_id.
     *
     * @param string|null $clientid
     * @return mixed|string
     */
    public function getEncryptionAlgorithm($clientid = null) {
        global $DB;

        // First try to get client-specific algorithm, then fall back to default (empty string).
        if ($clientid) {
            $alg = $DB->get_field('local_oauth2_public_key', 'encryption_algorithm', ['client_id' => $clientid]);
            if ($alg) {
                return $alg;
            }
        }

        // Fall back to default key (empty client_id).
        $alg = $DB->get_field('local_oauth2_public_key', 'encryption_algorithm', ['client_id' => '']);
        return $alg ? $alg : 'RS256';
    }
}
