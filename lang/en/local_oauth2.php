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
 * English language strings for local_oauth2.
 *
 * @package local_oauth2
 * @author Pau Ferrer Ocaña <pferre22@xtec.cat>
 * @author Lai Wei <lai.wei@enovation.ie>
 * @author Dorel Manolescu <dorel.manolescu@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2025 Enovation Solutions
 */

defined('MOODLE_INTERNAL') || die();

// phpcs:disable moodle.Files.LangFilesOrdering.IncorrectOrder -- The strings are organised by features.
// phpcs:disable moodle.Files.LangFilesOrdering.UnexpectedComment -- The strings are organised by features.

// General strings.
$string['pluginname'] = 'Oauth2 Server';

// Privacy Subsystem.
$string['privacy:metadata:local_oauth2_user_auth_scope'] = 'Information about the scopes that a user has granted to an OAuth2 client.';
$string['privacy:metadata:local_oauth2_user_auth_scope:user_id'] = 'The ID of the user who granted the scope.';
$string['privacy:metadata:local_oauth2_user_auth_scope:client_id'] = 'The ID of the OAuth2 client.';
$string['privacy:metadata:local_oauth2_user_auth_scope:scope'] = 'The scope that the user has granted to the OAuth2 client.';
$string['privacy:metadata:local_oauth2_access_token'] = 'Information about the access tokens issued to users.';
$string['privacy:metadata:local_oauth2_access_token:user_id'] = 'The ID of the user to whom the access token was issued.';
$string['privacy:metadata:local_oauth2_access_token:client_id'] = 'The ID of the OAuth2 client to which the access token was issued.';
$string['privacy:metadata:local_oauth2_access_token:scope'] = 'The scope of the access token.';
$string['privacy:metadata:local_oauth2_access_token:access_token'] = 'The access token issued to the user.';
$string['privacy:metadata:local_oauth2_access_token:expires'] = 'The expiration time of the access token.';
$string['privacy:metadata:local_oauth2_authorization_code'] = 'Information about the authorization codes issued to users.';
$string['privacy:metadata:local_oauth2_authorization_code:user_id'] = 'The ID of the user to whom the authorization code was issued.';
$string['privacy:metadata:local_oauth2_authorization_code:authorization_code'] = 'The authorization code issued to the user.';
$string['privacy:metadata:local_oauth2_authorization_code:client_id'] = 'The ID of the OAuth2 client to which the authorization code was issued.';
$string['privacy:metadata:local_oauth2_authorization_code:redirect_uri'] = 'The redirect URI of the OAuth2 client to which the authorization code was issued.';
$string['privacy:metadata:local_oauth2_authorization_code:expires'] = 'The expiration time of the authorization code.';
$string['privacy:metadata:local_oauth2_authorization_code:scope'] = 'The scope of the authorization code.';
$string['privacy:metadata:local_oauth2_authorization_code:id_token'] = 'The ID token issued to the user.';
$string['privacy:metadata:local_oauth2_refresh_token'] = 'Information about the refresh tokens issued to users.';
$string['privacy:metadata:local_oauth2_refresh_token:user_id'] = 'The ID of the user to whom the refresh token was issued.';
$string['prvacy:metadata:local_oauth2_refresh_token:refresh_token'] = 'The refresh token issued to the user.';
$string['privacy:metadata:local_oauth2_refresh_token:client_id'] = 'The ID of the OAuth2 client to which the refresh token was issued.';
$string['privacy:metadata:local_oauth2_refresh_token:expires'] = 'The expiration time of the refresh token.';
$string['privacy:metadata:local_oauth2_refresh_token:scope'] = 'The scope of the refresh token.';
$string['privacy:metadata:local_oauth2_refresh_token:refresh_token'] = 'The refresh token issued to the user.';

// Capabilities.
$string['oauth2:manage_oauth_clients'] = 'Manage OAuth clients';

// Events.
$string['event_user_granted'] = 'User granted access to OAuth2 server';
$string['event_user_not_granted'] = 'User did not grant access to OAuth2 server';
$string['event_access_token_created'] = 'Access token created';
$string['event_access_token_updated'] = 'Access token updated';
$string['event_access_token_deleted'] = 'Access token deleted';

// Tasks.
$string['task_cleanup'] = 'Clean up expired auth codes and tokens';

// OAuth client configuration.
$string['manage_oauth_clients'] = 'Manage OAuth clients';
$string['oauth_client_details'] = 'Client details';
$string['oauth_add_client'] = 'Add OAuth client';
$string['oauth_client_id'] = 'Client ID';
$string['oauth_client_secret'] = 'Client Secret';
$string['oauth_client_id_help'] = 'Client ID for the OAuth client.';
$string['oauth_redirect_uri'] = 'Redirect URI';
$string['oauth_redirect_uri_help'] = 'Redirect URI for the OAuth client.';
$string['oauth_redirect_uri_help_local_copilot'] = '<br/>
For Microsoft 365 Copilot integration, use: <b>https://teams.microsoft.com/api/platform/v1.0/oAuthRedirect</b>';
$string['oauth_scope'] = 'Scope';
$string['oauth_scope_help'] = 'Scope for the OAuth client.';
$string['oauth_scope_help_local_copilot'] = '<br/>
Separate multiple scopes with a space.</br>
For Microsoft 365 Copilot integration, this should be:
<ul>
<li><b>teacher.read teacher.write</b> for the teacher OAuth2 client</li>
<li><b>student.read student.write</b> for the student OAuth2 client</li>
</ul>';
$string['oauth_require_pkce'] = 'Require PKCE';
$string['oauth_require_pkce_help'] = 'Require PKCE (Proof Key for Code Exchange) for this client. PKCE provides additional security against authorization code interception attacks. Recommended for public clients such as mobile apps and single-page applications.';
$string['oauth_generate_secret'] = 'Generate client secret';
$string['oauth_generate_secret_help'] = 'Generate a client secret for this registration. Uncheck this to create a public client without a secret. Public clients must have PKCE enabled.';
$string['actions'] = 'Actions';
$string['oauth_client_not_exists'] = 'Oauth client does not exist';
$string['oauth_client_id_cannot_contain_space'] = 'Client ID cannot contain space';
$string['oauth_client_id_already_exists'] = 'Client ID already exists';
$string['oauth_client_changes_saved'] = 'OAuth client changes saved';
$string['delete_oauth_client_confirm'] = 'Are you sure you want to delete this OAuth client?';
$string['error_creating_oauth_client'] = 'Error occurred while creating OAuth client';
$string['error_deleting_oauth_client'] = 'Error occurred while deleting OAuth client';
$string['error_updating_oauth_client'] = 'Error occurred while updating OAuth client';
$string['not_implemented'] = 'Not implemented';
$string['settings_token_settings'] = 'Token settings';
$string['settings_access_token_lifetime'] = 'Access token lifetime';
$string['settings_access_token_lifetime_desc'] = 'The period of time that the access token is valid for';
$string['settings_refresh_token_lifetime'] = 'Refresh token lifetime';
$string['settings_refresh_token_lifetime_desc'] = 'The period of time that the refresh token is valid for';

// OAuth endpoints.
$string['oauth_userinfo_endpoint'] = 'UserInfo endpoint';
$string['oauth_userinfo_endpoint_desc'] = 'Returns OpenID Connect UserInfo claims about the authenticated user. Requires a valid access token with the openid scope.';

// OAuth scopes.
$string['oauth_scope_openid'] = 'OpenID authentication';
$string['oauth_scope_profile'] = 'Profile information (name, picture, etc.)';
$string['oauth_scope_email'] = 'Email address';
$string['oauth_scope_address'] = 'Address information';
$string['oauth_scope_phone'] = 'Phone number';

// OAuth client configuration.
$string['oauth_auth_question'] = 'Do you want to authorize application <b>{$a}</b> to access your Moodle account?</br>';
$string['oauth_scope_list'] = 'The application is to access the following data: ';
$string['oauth_scope_login'] = 'Login';
