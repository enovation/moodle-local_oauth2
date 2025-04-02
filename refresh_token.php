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
 * OAuth authentication refresh token endpoint.
 *
 * @package local_oauth2
 * @author Lai Wei <lai.wei@enovation.ie>
 * @author Dorel Manolescu <dorel.manolescu@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2025 Enovation Solutions
 */

use OAuth2\Request;
use OAuth2\Response;

// phpcs:ignore moodle.Files.RequireLogin.Missing -- This file is refresh token endpoint, no need to require login.
require_once(__DIR__ . '/../../config.php');

try {
    $server = local_oauth2\utils::get_oauth_server();

    $request = Request::createFromGlobals();
    $response = new Response();

    $server->handleTokenRequest($request, $response)->send();
} catch (Exception $e) {
    // phpcs:ignore moodle.security.outputnotprotected.exception -- This is an API endpoint, we need to return the error.
    // Log the error to Moodle error log if debugging is enabled.
    if (debugging('', DEBUG_DEVELOPER)) {
        debugging('OAuth error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    $response = new Response();
    $response->setError(500, 'An unexpected error occurred: ' . $e->getMessage());
    $response->send();
}
