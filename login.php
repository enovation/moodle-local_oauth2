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
 * OAuth2 authentication authorization endpoint.
 *
 * @package local_oauth2
 * @author Pau Ferrer Ocaña <pferre22@xtec.cat>
 * @author Lai Wei <lai.wei@enovation.ie>
 * @author Dorel Manolescu <dorel.manolescu@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2025 Enovation Solutions
 */

use local_oauth2\event\user_granted;
use local_oauth2\event\user_not_granted;

// phpcs:ignore moodle.Files.RequireLogin.Missing -- This file is used to log in users.
require_once(__DIR__ . '/../../config.php');

$clientid = required_param('client_id', PARAM_TEXT);
$responsetype = required_param('response_type', PARAM_TEXT);
$scope = optional_param('scope', false, PARAM_TEXT);
$state = optional_param('state', false, PARAM_TEXT);
$codechallenge = optional_param('code_challenge', false, PARAM_TEXT);
$codechallengemethod = optional_param('code_challenge_method', false, PARAM_ALPHANUMEXT);
$redirecturi = optional_param('redirect_uri', false, PARAM_URL);
$url = new moodle_url('/local/oauth2/login.php', ['client_id' => $clientid, 'response_type' => $responsetype]);

if ($scope) {
    $url->param('scope', $scope);
}

if ($state) {
    $url->param('state', $state);
}

if ($codechallenge) {
    $url->param('code_challenge', $codechallenge);
}

if ($codechallengemethod) {
    $url->param('code_challenge_method', $codechallengemethod);
}

if ($redirecturi) {
    $url->param('redirect_uri', $redirecturi);
}

$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('login');

if (isloggedin() && !isguestuser()) {
    $server = local_oauth2\utils::get_oauth_server();

    // Merge GET and POST parameters for PKCE support.
    // After consent form submission, PKCE params may be in POST.
    $queryparams = $_GET;
    if (!empty($_POST['code_challenge'])) {
        $queryparams['code_challenge'] = $_POST['code_challenge'];
    }
    if (!empty($_POST['code_challenge_method'])) {
        $queryparams['code_challenge_method'] = $_POST['code_challenge_method'];
    }

    $request = new OAuth2\Request($queryparams, $_POST, [], $_COOKIE, $_FILES, $_SERVER);
    $response = new OAuth2\Response();

    // Check if PKCE is required for this client.
    $pkceenforced = local_oauth2\utils::is_pkce_required($clientid);
    if ($pkceenforced) {
        $reqcodechallenge = $request->query('code_challenge');
        if (empty($reqcodechallenge)) {
            $response->setError(400, 'invalid_request', 'Missing code_challenge parameter. PKCE is required for this client.');
            $response->send();
            die();
        }
        $reqcodechallengemethod = $request->query('code_challenge_method', 'plain');
        if (!in_array($reqcodechallengemethod, ['plain', 'S256'])) {
            $response->setError(400, 'invalid_request', 'Invalid code_challenge_method. Must be "plain" or "S256".');
            $response->send();
            die();
        }
    }

    if (!$server->validateAuthorizeRequest($request, $response)) {
        $logparams = ['objectid' => $USER->id, 'other' => ['clientid' => $clientid, 'scope' => $scope]];

        $event = user_not_granted::create($logparams);
        $event->trigger();

        $response->send();
        die();
    }

    // Pass PKCE parameters to the consent form as hidden fields.
    $formcustomdata = [];
    if ($codechallenge) {
        $formcustomdata['code_challenge'] = $codechallenge;
    }
    if ($codechallengemethod) {
        $formcustomdata['code_challenge_method'] = $codechallengemethod;
    }

    $isauthorized = local_oauth2\utils::get_authorization_from_form($url, $clientid, $scope, $formcustomdata);

    $logparams = ['objectid' => $USER->id, 'other' => ['clientid' => $clientid, 'scope' => $scope]];
    if ($isauthorized) {
        $event = user_granted::create($logparams);
        $event->trigger();
    } else {
        $event = user_not_granted::create($logparams);
        $event->trigger();
    }

    $server->handleAuthorizeRequest($request, $response, $isauthorized, $USER->id);
    $response->send();
} else {
    $SESSION->wantsurl = $url;
    redirect(new moodle_url('/login/index.php'));
}
