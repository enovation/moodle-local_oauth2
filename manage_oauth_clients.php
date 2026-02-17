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
 * This page allows authorised users to manage OAuth2 clients.
 *
 * @package local_oauth2
 * @author Pau Ferrer Ocaña <pferre22@xtec.cat>
 * @author Lai Wei <lai.wei@enovation.ie>
 * @author Dorel Manolescu <dorel.manolescu@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2025 Enovation Solutions
 */

use local_oauth2\form\oauth_client_form;
use local_oauth2\table\oauth_clients_table;
use local_oauth2\utils;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('local/oauth2:manage_oauth_clients', context_system::instance());

admin_externalpage_setup('local_oauth2_manage_oauth_clients');

$action = optional_param('action', '', PARAM_ALPHANUMEXT);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_oauth_clients', 'local_oauth2'));

$viewtable = true;

switch ($action) {
    case 'edit':
        $id = required_param('id', PARAM_INT);
        if (!$clientrecordtoedit = $DB->get_record('local_oauth2_client', ['id' => $id])) {
            echo $OUTPUT->notification(get_string('oauth_client_not_exists', 'local_oauth2'));
            $viewtable = true;
            break;
        }
        $viewtable = false;
        // Fall through.

    case 'add':
        $form = new oauth_client_form();
        if ($form->is_cancelled()) {
            $viewtable = true;
            break;
        }

        // Save form data.
        if (($fromform = $form->get_data()) && confirm_sesskey()) {
            $clientrecord = new stdClass();
            if ($action === 'add') {
                $clientrecord->client_id = $fromform->client_id;
            }
            $clientrecord->redirect_uri = $fromform->redirect_uri;
            $clientrecord->scope = $fromform->scope;
            $clientrecord->require_pkce = $fromform->require_pkce;

            if (!isset($clientrecordtoedit)) {
                // Generate secret unless PKCE is enabled and user opted out.
                // If PKCE is not enabled, always generate a secret.
                if (empty($fromform->require_pkce) || !empty($fromform->generate_secret)) {
                    $clientrecord->client_secret = utils::generate_secret();
                } else {
                    $clientrecord->client_secret = '';
                }

                if (!$clientrecord->id = $DB->insert_record('local_oauth2_client', $clientrecord)) {
                    throw new moodle_exception('error_creating_oauth_client', 'local_oauth2');
                }
            } else {
                $clientrecord->id = $clientrecordtoedit->id;
                if (!$DB->update_record('local_oauth2_client', $clientrecord)) {
                    throw new moodle_exception('error_updating_oauth_client', 'local_oauth2');
                }
            }

            echo $OUTPUT->notification(get_string('oauth_client_changes_saved', 'local_oauth2'), 'notifysuccess');
            $viewtable = true;
            break;
        }

        // Prepare form data.
        if (isset($clientrecordtoedit)) {
            $formdata = new stdClass();
            $formdata->client_id = $clientrecordtoedit->client_id;
            $formdata->redirect_uri = $clientrecordtoedit->redirect_uri;
            $formdata->scope = $clientrecordtoedit->scope;
            $formdata->require_pkce = $clientrecordtoedit->require_pkce;
            $formdata->action = 'edit';
        } else {
            $formdata = new stdClass();
            $formdata->client_id = '';
            $formdata->redirect_uri = '';
            $formdata->scope = '';
            $formdata->require_pkce = 0;
            $formdata->action = 'add';
        }
        $form->set_data($formdata);
        $form->display();
        $viewtable = false;

        break;

    case 'delete':
        $confirm = optional_param('confirm', 0, PARAM_INT);
        $id = required_param('id', PARAM_INT);

        if (empty($confirm)) {
            if (!$clientrecordtodelete = $DB->get_record('local_oauth2_client', ['id' => $id])) {
                echo $OUTPUT->notification(get_string('oauth_client_not_exists', 'local_oauth2'));
                $viewtable = true;
                break;
            }

            $viewtable = false;
            echo $OUTPUT->confirm(
                get_string('delete_oauth_client_confirm', 'local_oauth2'),
                new moodle_url('/local/oauth2/manage_oauth_clients.php', ['action' => 'delete', 'id' => $id, 'confirm' => 1]),
                new moodle_url('/local/oauth2/manage_oauth_clients.php')
            );
        } else {
            if (!$DB->delete_records('local_oauth2_client', ['id' => $id])) {
                throw new moodle_exception('error_deleting_oauth_client', 'local_oauth2');
            }

            echo $OUTPUT->notification(get_string('oauth_client_changes_saved', 'local_oauth2'), 'notifysuccess');
            $viewtable = true;
        }

        break;

    default:
        $viewtable = true;
        break;
}

if ($viewtable) {
    // Button to add OAuth client.
    echo html_writer::tag(
        'p',
        html_writer::link(
            new moodle_url('/local/oauth2/manage_oauth_clients.php', ['action' => 'add']),
            get_string('oauth_add_client', 'local_oauth2')
        )
    );

    // Display OAuth clients table.
    $table = new oauth_clients_table();
    $table->out();
}

echo $OUTPUT->footer();
