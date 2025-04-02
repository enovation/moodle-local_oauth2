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
 * Authorize form.
 *
 * @package local_oauth2
 * @author Pau Ferrer Ocaña <pferre22@xtec.cat>
 * @author Lai Wei <lai.wei@enovation.ie>
 * @author Dorel Manolescu <dorel.manolescu@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2025 Enovation Solutions
 */

namespace local_oauth2\form;

use html_writer;
use moodleform;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Authorize form.
 */
class authorize_form extends moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform =& $this->_form;

        $clientid = required_param('client_id', PARAM_TEXT);

        $authquestiontext = get_string('oauth_auth_question', 'local_oauth2', $clientid);
        $mform->addElement('html', $authquestiontext);
        $scope = optional_param('scope', '', PARAM_TEXT);
        $scopetext = get_string('oauth_scope_list', 'local_oauth2');
        if (!empty($scope)) {
            $scopes = explode(' ', $scope);
            $scopetext .= html_writer::start_tag('ul');
            foreach ($scopes as $scope) {
                $scopetext .= html_writer::start_tag('li') . $scope . html_writer::end_tag('li');
            }
            $scopetext .= html_writer::end_tag('li');
        } else {
            $scopetext .= get_string('oauth_scope_login', 'local_oauth2');
        }
        $mform->addElement('html', $scopetext);

        $this->add_action_buttons(true, get_string('confirm'));
    }
}
