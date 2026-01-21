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
 * OAuth authentication token endpoint.
 *
 * @package local_oauth2
 * @author Pau Ferrer Ocaña <pferre22@xtec.cat>
 * @author Lai Wei <lai.wei@enovation.ie>
 * @author Dorel Manolescu <dorel.manolescu@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2025 Enovation Solutions
 */

use core\session\manager;
use OAuth2\Request;

// phpcs:ignore moodle.Files.RequireLogin.Missing -- This file is token endpoint, no need to require login.
require_once(__DIR__ . '/../../config.php');

manager::write_close();

$server = local_oauth2\utils::get_oauth_server();

$request = Request::createFromGlobals();
$response = new OAuth2\Response();

// Debug: log the request details
error_log('OAuth2 Request scope: ' . $request->request('scope'));
error_log('OAuth2 Request grant_type: ' . $request->request('grant_type'));

$server->handleTokenRequest($request, $response);

// Debug: log the response details
error_log('OAuth2 Response: ' . $response->getStatusCode() . ' - ' . json_encode($response->getParameters()));

$response->send();
