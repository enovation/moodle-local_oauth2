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
 * OAuth2 UserInfo endpoint.
 *
 * This endpoint returns OpenID Connect UserInfo claims about the authenticated user.
 * It requires a valid access token with the 'openid' scope.
 *
 * @package    local_oauth2
 * @author     Lai Wei <lai.wei@enovation.ie>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright  (C) 2026 Enovation Solutions
 */

use core\session\manager;
use OAuth2\Request;
use OAuth2\Response;

// phpcs:ignore moodle.Files.RequireLogin.Missing -- This is an OAuth2 endpoint, no login required.
require_once(__DIR__ . '/../../config.php');

// Set page context for API endpoint.
$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/oauth2/userinfo.php');

// Close the session to prevent session locking.
manager::write_close();

// Get the OAuth2 server instance.
$server = local_oauth2\utils::get_oauth_server();

// Create request and response objects.
$request = Request::createFromGlobals();
$response = new Response();

// Handle the UserInfo request.
$server->handleUserInfoRequest($request, $response);

// Send the response.
$response->send();
