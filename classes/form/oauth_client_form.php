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
 * Create or edit OAuth2 client form.
 *
 * @package local_oauth2
 * @author Pau Ferrer Ocaña <pferre22@xtec.cat>
 * @author Lai Wei <lai.wei@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2024 onwards Microsoft, Inc. (http://microsoft.com/)
 */

namespace local_oauth2\form;

use local_oauth2\utils;
use moodleform;

/**
 * Create or edit OAuth client form.
 */
class oauth_client_form extends moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform =& $this->_form;

        // Action.
        $mform->addElement('hidden', 'action', 'add');
        $mform->setType('action', PARAM_ALPHA);

        // Client details header.
        $mform->addElement('header', 'clientdetails', get_string('oauth_client_details', 'local_oauth2'));
        $mform->setExpanded('clientdetails');

        // Client ID.
        $mform->addElement('text', 'client_id', get_string('oauth_client_id', 'local_oauth2'));
        $mform->setType('client_id', PARAM_TEXT);
        $mform->addElement('static', 'client_id_help', '', get_string('oauth_client_id_help', 'local_oauth2'));

        $action = optional_param('action', '', PARAM_ALPHA);
        if ($action === 'edit') {
            $id = required_param('id', PARAM_INT);
            $mform->addElement('hidden', 'id', $id);
            $mform->setType('id', PARAM_INT);

            $mform->freeze('client_id');
        } else {
            $mform->addRule('client_id', get_string('required'), 'required');
        }

        // Redirect URI.
        $mform->addElement('text', 'redirect_uri', get_string('oauth_redirect_uri', 'local_oauth2'), ['size' => 80]);
        $mform->setType('redirect_uri', PARAM_URL);
        $mform->setDefault('redirect_uri', 'https://teams.microsoft.com/api/platform/v1.0/oAuthRedirect');
        $redirecturihelptext = get_string('oauth_redirect_uri_help', 'local_oauth2');
        if (utils::is_local_copilot_installed()) {
            $redirecturihelptext .= get_string('oauth_redirect_uri_help_local_copilot', 'local_oauth2');
        }
        $mform->addElement('static', 'redirect_uri_help', '', $redirecturihelptext);

        // Scope.
        $mform->addElement('text', 'scope', get_string('oauth_scope', 'local_oauth2'), ['size' => 80]);
        $mform->setType('scope', PARAM_TEXT);
        $scopehelptext = get_string('oauth_scope_help', 'local_oauth2');
        if (utils::is_local_copilot_installed()) {
            $scopehelptext .= get_string('oauth_scope_help_local_copilot', 'local_oauth2');
        }
        $mform->addElement('static', 'scope_help', '', $scopehelptext);

        // Require PKCE.
        $mform->addElement('advcheckbox', 'require_pkce', get_string('oauth_require_pkce', 'local_oauth2'));
        $mform->setType('require_pkce', PARAM_INT);
        $mform->setDefault('require_pkce', 0);
        $mform->addElement('static', 'require_pkce_help', '', get_string('oauth_require_pkce_help', 'local_oauth2'));

        // Freeze require_pkce when editing - cannot be changed after creation.
        if ($action === 'edit') {
            $mform->freeze('require_pkce');
        }

        // Generate client secret - only shown when creating a new client.
        if ($action !== 'edit') {
            $mform->addElement('advcheckbox', 'generate_secret', get_string('oauth_generate_secret', 'local_oauth2'));
            $mform->setType('generate_secret', PARAM_INT);
            $mform->setDefault('generate_secret', 1);
            $mform->addElement('static', 'generate_secret_help', '', get_string('oauth_generate_secret_help', 'local_oauth2'));

            // Disable generate_secret unless require_pkce is enabled.
            $mform->disabledIf('generate_secret', 'require_pkce', 'eq', 0);
        }

        // Action buttons.
        $this->add_action_buttons();
    }

    /**
     * Custom form validation - avoid duplicate client IDs.
     *
     * @param array $data Form data.
     * @param array $files Form files.
     * @return array Errors.
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        // Field client_id cannot have space.
        if ($data['action'] == 'add' && strpos($data['client_id'], ' ') !== false) {
            $errors['client_id'] = get_string('oauth_client_id_cannot_contain_space', 'local_oauth2');
        }

        // A new client_id to add must not already exist.
        $action = optional_param('action', '', PARAM_ALPHA);
        if ($action === 'add' && $DB->record_exists('local_oauth2_client', ['client_id' => $data['client_id']])) {
            $errors['client_id'] = get_string('oauth_client_id_already_exists', 'local_oauth2');
        }

        return $errors;
    }
}
