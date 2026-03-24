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
 * JWKS endpoint.
 *
 * @package    local_oauth2
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\session\manager;

// phpcs:ignore moodle.Files.RequireLogin.Missing -- This is an OAuth2 endpoint, no login required.
require_once(__DIR__ . '/../../config.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/oauth2/jwks.php');

manager::write_close();

header('Content-Type: application/json');
header('Cache-Control: public, max-age=300');

try {
    echo json_encode(local_oauth2\utils::get_jwks(), JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    debugging('local_oauth2 JWKS error: ' . $exception->getMessage(), DEBUG_DEVELOPER);
    http_response_code(500);
    echo json_encode([
        'error' => 'server_error',
        'error_description' => 'The server was unable to complete the request.',
    ], JSON_UNESCAPED_SLASHES);
}