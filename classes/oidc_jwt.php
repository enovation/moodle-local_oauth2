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
 * JWT helper for OIDC ID tokens with explicit kid support.
 *
 * @package    local_oauth2
 * @author     Microsoft
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2026 Enovation Solutions
 */

namespace local_oauth2;

/**
 * JWT helper for OIDC ID token signing.
 */
class oidc_jwt extends \OAuth2\Encryption\Jwt {
    /**
     * @var string|null
     */
    protected $keyid;

    /**
     * Encode a payload and include a kid header.
     *
     * @param array $payload
     * @param string $key
     * @param string $algorithm
     * @param string|null $keyid
     * @return string
     */
    public function encode_with_key_id(array $payload, string $key, string $algorithm = 'HS256', ?string $keyid = null): string {
        $this->keyid = $keyid;

        try {
            return parent::encode($payload, $key, $algorithm);
        } finally {
            $this->keyid = null;
        }
    }

    // phpcs:disable moodle.NamingConventions.ValidFunctionName.LowercaseMethod -- overrides parent method from external library.
    /**
     * Generate a JWT header with an optional kid.
     *
     * @param array $payload
     * @param string $algorithm
     * @return array
     */
    protected function generateJwtHeader($payload, $algorithm) {
        $header = parent::generateJwtHeader($payload, $algorithm);

        if (!empty($this->keyid)) {
            $header['kid'] = $this->keyid;
        }

        return $header;
    }
    // phpcs:enable moodle.NamingConventions.ValidFunctionName.LowercaseMethod
}
