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
 * OIDC ID token response type with kid-aware signing.
 *
 * @package    local_oauth2
 * @author     Microsoft
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oauth2\response_type;

use local_oauth2\oidc_jwt;

/**
 * ID token response type that emits kid in JWT headers.
 */
class id_token extends \OAuth2\OpenID\ResponseType\IdToken {
    /**
     * Encode an ID token and add a kid header when a signing key is available.
     *
     * @param array $token
     * @param string|null $client_id
     * @return string
     */
    protected function encodeToken(array $token, $client_id = null) {
        $privatekey = $this->publicKeyStorage->getPrivateKey($client_id);
        $algorithm = $this->publicKeyStorage->getEncryptionAlgorithm($client_id);
        $kid = method_exists($this->publicKeyStorage, 'getKeyId')
            ? $this->publicKeyStorage->getKeyId($client_id)
            : null;

        $jwt = new oidc_jwt();
        return $jwt->encode_with_key_id($token, $privatekey, $algorithm, $kid);
    }
}