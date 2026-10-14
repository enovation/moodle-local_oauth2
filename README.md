# OAuth2 Server Plugin for Moodle

It provides an [OAuth2](https://tools.ietf.org/html/rfc6749 "RFC6749") server so that a user can use its Moodle account to log in to external applications.
Oauth2 Library has been taken from https://github.com/bshaffer/oauth2-server-php

> **Important: this plugin will be retired.**
> Moodle 5.3 core now ships with an OAuth 2 server feature that provides the same functionality as this plugin.
> On Moodle 5.3 and later, use the core feature instead of installing this plugin, unless it's specifically required by a plugin that has not yet been updated to use the core feature.
> This plugin will be retired in the near future, once the earlier Moodle versions that still depend on it are all out of support.

## Requirements
* #### Moodle 5.3 installed (this branch supports Moodle 5.3 only; use the matching `MOODLE_xxx_STABLE` branch for other Moodle versions)
* #### Admin account

## Installation steps
1. Download the plugin from Moodle plugins directory or from GitHub repository.

2. Extract the files if you downloaded a zip file.

3. Create a folder "oauth2" in the "public/local" directory of your Moodle installation (Moodle 5.1 and later serve the site from the "public" directory). Copy the files from the plugin into this folder.

4. Login to the site as site administrator.

5. Go to *Site Administration > Server > OAuth2 server > Manage OAuth clients*

6. Click *Add OAuth client*

7. Fill in the form. Your Client Identifier and Client Secret (which will be given later) will be used for you to authenticate. The Redirect URL must be the URL mapping to your client that will be used.
   - **Generate client secret:** leave it checked for a confidential client. Uncheck it to create a public client without a secret (for example a single-page or native application). Public clients authenticate to the token endpoint with `client_id` only, so enable *Require PKCE* for them.
   - **Require PKCE:** when enabled, authorization requests for this client must include a `code_challenge` with `code_challenge_method=S256` (and the token request the matching `code_verifier`). The `plain` method is not supported, and any request that sends a `code_challenge` must use `S256`, even if PKCE is not required for the client.
   - **Scope:** the scopes the client is allowed to request, separated by a space.

## Plugin settings

Go to *Site Administration > Server > OAuth2 server > Token settings* to configure:

- **Access token lifetime** - how long an access token is valid (default: 1 hour).
- **Refresh token lifetime** - how long a refresh token is valid (default: 1 week).
- **Enable password grant** - allow the OAuth2 password grant (disabled by default, see [Password grant](#password-grant)).
- **OIDC issuer** - optional public issuer URL used in the discovery document and in ID tokens. Leave empty to use `$CFG->wwwroot`.

Expired authorization codes and tokens are removed by the scheduled task *Clean up expired auth codes and tokens*.

## How to get an access token

1. From your application, redirect the user to this URL: `http://moodledomain.com/local/oauth2/login.php?client_id=EXAMPLE&response_type=code&redirect_uri=REDIRECT_URI&state=RANDOM_STATE` *(remember to replace the URL domain with the domain of Moodle and replace EXAMPLE with the Client Identifier given in the form.)* The `state` parameter is required: requests without it are rejected with an `invalid_request` error. Send a random value and verify it when the user is redirected back. When using OpenID Connect, also send a `nonce`, and send a `code_challenge` with `code_challenge_method=S256` if the client requires PKCE.

2. The user must log in to Moodle and authorize your application to use its basic info.

3. If it went all OK, the plugin should redirect the user to something like: `http://yourapplicationdomain.com/foo?code=55c057549f29c428066cbbd67ca6b17099cb1a9e` *(that's a GET request to the Redirect URI given with the code parameter)*

4. Using the code given, your application must send a POST request to `http://moodledomain.com/local/oauth2/token.php`  with the following parameters: `{'code': '55c057549f29c428066cbbd67ca6b17099cb1a9e', 'client_id': 'EXAMPLE', 'client_secret': 'codeGivenAfterTheFormWasFilled', 'grant_type': 'authorization_code', 'scope': '[SCOPES SEPARATED BY SPACE]'}`. For public clients omit `client_secret`, and for PKCE add `'code_verifier': '[VERIFIER]'`.

5. If the correct credentials were given, the response is a JSON like this: `{"access_token":"79d687a0ea4910c6662b2e38116528fdcd65f0d1","expires_in":3600,"token_type":"Bearer","scope":"[SCOPES]","refresh_token":"c1de730eef1b2072b48799000ec7cde4ea6d2af0"}`

6. **Using the OAuth2 Access Token:**
   - The OAuth2 `access_token` can be used to access **OAuth2-protected endpoints** like the UserInfo endpoint (`/local/oauth2/userinfo.php`)
   - **Note:** The OAuth2 access token **cannot be used directly** to call Moodle web services (external API)
   - To enable web service calls, you need to create a plugin that:
     1. Listens to the `\local_oauth2\event\access_token_created` and `\local_oauth2\event\access_token_updated` events
     2. Maps the OAuth2 access token to an entry in Moodle's `external_tokens` table
     3. See the [local_copilot plugin](https://github.com/microsoft/moodle-local_copilot) for a complete example implementation
   - The `refresh_token` is used to get a new access_token when the current one expires

The `expires_in` value follows the *Access token lifetime* setting (1 hour by default).

### Password grant

The Resource Owner Password Credentials grant (`grant_type=password` with `username` and `password`) is **disabled by default**. To enable it, tick *Enable password grant* in *Site Administration > Server > OAuth2 server > Token settings*. When enabled, the discovery document advertises it. It exposes user credentials to the client and bypasses the consent screen and multi-factor authentication, so only use it with fully trusted applications and prefer the authorization code flow.

**Upgrading:** in earlier releases the password grant was always available. Sites that rely on it must enable *Enable password grant* after upgrading, otherwise token requests with `grant_type=password` fail with an `unsupported_grant_type` error.

Note: If testing in Postman, you need to set encoding to `x-www-form-urlencoded` for POST requests.

## How to use the refresh token

When the access_token expires, your application must request a new one using the refresh_token.

1. Endpoint
Send a POST request to: `http://moodledomain.com/local/oauth2/refresh_token.php`  with the following
2. Request parameters: `{'client_id': 'EXAMPLE', 'client_secret': 'codeGivenAfterTheFormWasFilled', 'grant_type': 'refresh_token', 'refresh_token': 'c1de730eef1b2072b48799000ec7cde4ea6d2af0'}`.
(The refresh token is returned together with the access token, see step 5 of "How to get an access token".)

3. Response
If the request is successful, the response will contain a new access token and a new refresh token:
`{
    "access_token": "1703c39b0a9e462e2430a2e53da3299696bdefd5",
    "expires_in": 3600,
    "token_type": "Bearer",
    "scope": "[SCOPES SEPARATED BY SPACE]",
    "refresh_token": "c3150439e43649595a7b753ee1e99e041ee6aa0a"
}`.

4. Implementation Notes
   - Use the new access_token for API requests.
   - Replace the old refresh_token with the new one provided in the response.
   - If the refresh_token itself expires, the user must authenticate again to obtain new credentials.

## How to get user information (UserInfo endpoint)

The plugin provides an OpenID Connect UserInfo endpoint that returns claims about the authenticated user.

1. Endpoint
   Send a GET or POST request to: `http://moodledomain.com/local/oauth2/userinfo.php`

2. Request Headers
   Include the access token in the Authorization header:
   ```
   Authorization: Bearer {access_token}
   ```

3. **Important: Requesting the openid Scope**

   To use the UserInfo endpoint, you MUST include the `openid` scope when requesting the access token:

   When redirecting users for authorization:
   ```
   http://moodledomain.com/local/oauth2/login.php?client_id=EXAMPLE&response_type=code&scope=openid profile email
   ```

   When exchanging the authorization code for tokens:
   ```json
   {
       "code": "55c057549f29c428066cbbd67ca6b17099cb1a9e",
       "client_id": "EXAMPLE",
       "client_secret": "codeGivenAfterTheFormWasFilled",
       "grant_type": "authorization_code",
       "scope": "openid profile email"
   }
   ```

4. Response
   If the request is successful and the access token has the 'openid' scope, the response will contain user claims:
   ```json
   {
       "sub": "123",
       "name": "John Doe",
       "email": "john.doe@example.com",
       "email_verified": true,
       "given_name": "John",
       "family_name": "Doe",
       "picture": "https://moodledomain.com/pluginfile.php/..."
   }
   ```

5. Requirements
   - The access token must be valid and not expired
   - The access token MUST have been issued with the 'openid' scope
   - The user account associated with the token must still exist and be active

6. Available Scopes
   The plugin supports the following OpenID Connect scopes:
   - `openid` (required) - Returns the 'sub' claim with the user ID
   - `profile` - Returns profile claims (name, given_name, family_name, etc.)
   - `email` - Returns email and email_verified claims
   - `offline_access` - Allows OIDC clients to receive a refresh token during Authorization Code flow
   - `address` - Returns address-related claims
   - `phone` - Returns phone number claims

   ### Detailed Claim Mapping

   | Scope | Specific Claims Returned | Moodle Field Source |
   |-------|-------------------------|---------------------|
   | `openid` (required) | `sub` | User ID (always returned) |
   | `profile` | `name` | Full name (firstname + lastname) |
   | | `given_name` | firstname |
   | | `family_name` | lastname |
   | | `middle_name` | middlename |
   | | `nickname` | alternatename |
   | | `preferred_username` | username |
   | | `profile` | Profile URL |
   | | `picture` | Profile picture URL |
   | | `website` | url |
   | | `gender` | Not available in Moodle (returns null) |
   | | `birthdate` | Not available in Moodle (returns null) |
   | | `zoneinfo` | timezone |
   | | `locale` | lang |
   | | `updated_at` | timemodified (timestamp) |
   | `email` | `email` | email |
   | | `email_verified` | Based on emailstop field |
   | `address` | `formatted` | Limited address data |
   | | `street_address` | Limited address data |
   | | `locality` | city |
   | | `region` | Limited address data |
   | | `postal_code` | Limited address data |
   | | `country` | country |
   | `phone` | `phone_number` | phone1 or phone2 |
   | | `phone_number_verified` | Always false (Moodle doesn't verify phones) |

   **Note:** Some OpenID Connect standard claims (gender, birthdate) are not available in standard Moodle user fields and will return null.

   ### Example Responses by Scope

   **Minimal (openid only):**
   ```json
   {
       "sub": "2"
   }
   ```

   **Common (openid + profile + email):**
   ```json
   {
       "sub": "2",
       "name": "John Doe",
       "given_name": "John",
       "family_name": "Doe",
       "preferred_username": "johndoe",
       "profile": "https://moodledomain.com/user/profile.php?id=2",
       "picture": "https://moodledomain.com/theme/image.php/boost/core/1769426826/u/f1",
       "locale": "en",
       "zoneinfo": "99",
       "updated_at": 1754660588,
       "email": "john.doe@example.com",
       "email_verified": true
   }
   ```

   **Full (all scopes):**
   ```json
   {
       "sub": "2",
       "name": "John Doe",
       "given_name": "John",
       "family_name": "Doe",
       "middle_name": "",
       "nickname": "",
       "preferred_username": "johndoe",
       "profile": "https://moodledomain.com/user/profile.php?id=2",
       "picture": "https://moodledomain.com/theme/image.php/boost/core/1769426826/u/f1",
       "website": null,
       "gender": null,
       "birthdate": null,
       "zoneinfo": "99",
       "locale": "en",
       "updated_at": 1754660588,
       "email": "john.doe@example.com",
       "email_verified": true,
       "phone_number": "+1234567890",
       "phone_number_verified": false
   }
   ```

7. Implementation Notes
   - The 'sub' (subject) claim is always returned and represents the Moodle user ID
   - Available claims depend on the scopes requested during authorization
   - This endpoint follows the OpenID Connect UserInfo specification
   - If you get an "insufficient_scope" error, ensure you requested the 'openid' scope when obtaining the access token
   - **Best practice:** Request `openid profile email` for most applications

## Custom Scopes

The plugin supports custom scopes beyond the standard OpenID Connect scopes. The following custom scopes are included for Microsoft 365 Copilot integration:

- `teacher.read` - Read teacher information
- `teacher.write` - Modify teacher information
- `student.read` - Read student information
- `student.write` - Modify student information

### Adding Custom Scope Descriptions

To add human-readable descriptions for your own custom scopes:

1. Edit `local/oauth2/lang/en/local_oauth2.php`
2. Add language strings in the format `oauth_scope_<scopename>`:

```php
// Custom scopes.
$string['oauth_scope_mycustom.read'] = 'Read custom data';
$string['oauth_scope_mycustom.write'] = 'Modify custom data';
```

3. Clear Moodle caches:
```bash
php admin/cli/purge_caches.php
```

The authorization consent screen will automatically display these descriptions when users authorize your application.

## Integration with Moodle Web Services

The OAuth2 access tokens generated by this plugin are designed for OAuth2-protected endpoints (like the UserInfo endpoint). They **cannot be used directly** to call Moodle's standard web services API.

### Why OAuth2 Tokens Don't Work Directly with Web Services

Moodle's web services (`/webservice/rest/server.php`, `/webservice/xmlrpc/server.php`, etc.) expect tokens from the `external_tokens` table, not OAuth2 access tokens. These are two different authentication systems:

- **OAuth2 tokens** (stored in `local_oauth2_access_token`) - Used for OAuth2 flows and OpenID Connect
- **Web service tokens** (stored in `external_tokens`) - Used for Moodle's external API/web services

### How to Enable Web Service Calls with OAuth2

To allow OAuth2-authenticated users to call Moodle web services, you need to create a plugin that bridges these two systems using event observers:

#### Example Implementation (from local_copilot plugin)

**1. Define event observers** (`db/events.php`):
```php
$observers = [
    [
        'eventname' => '\local_oauth2\event\access_token_created',
        'callback' => '\local_copilot\observers::handle_access_token_created_or_updated',
        'priority' => 200,
        'internal' => false,
    ],
    [
        'eventname' => '\local_oauth2\event\access_token_updated',
        'callback' => '\local_copilot\observers::handle_access_token_created_or_updated',
        'priority' => 200,
        'internal' => false,
    ],
];
```

**2. Implement the observer** (`classes/observers.php`):
```php
public static function handle_access_token_created_or_updated($event) {
    global $DB;
    
    $data = $event->get_data();
    $userid = $data['userid'];
    $token = $data['other']['accesstoken'];
    $validuntil = $data['other']['expires'];
    $clientid = $data['other']['clientid'];
    
    // Get your plugin's web service ID
    $externalserviceid = $DB->get_field(
        'external_services',
        'id',
        ['component' => 'local_yourplugin', 'shortname' => 'your_webservices'],
        MUST_EXIST
    );
    
    // Check if token already exists
    if ($externaltoken = $DB->get_record('external_tokens', 
        ['userid' => $userid, 'externalserviceid' => $externalserviceid])) {
        // Update existing token
        $externaltoken->token = $token;
        $externaltoken->validuntil = $validuntil;
        $externaltoken->timecreated = time();
        $DB->update_record('external_tokens', $externaltoken);
    } else {
        // Create new token
        $externaltoken = new stdClass();
        $externaltoken->userid = $userid;
        $externaltoken->externalserviceid = $externalserviceid;
        $externaltoken->token = $token;  // Use OAuth2 token as web service token
        $externaltoken->tokentype = EXTERNAL_TOKEN_PERMANENT;
        $externaltoken->contextid = context_system::instance()->id;
        $externaltoken->creatorid = $userid;
        $externaltoken->validuntil = $validuntil;
        $externaltoken->timecreated = time();
        $externaltoken->iprestriction = '';
        $DB->insert_record('external_tokens', $externaltoken);
    }
}
```

**3. Define your web services** (`db/services.php`):
```php
$services = [
    'your_webservices' => [
        'functions' => ['your_plugin_function1', 'your_plugin_function2'],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'your_webservices',
        'downloadfiles' => 0,
        'uploadfiles' => 0,
    ],
];
```

### Complete Example

For a complete, production-ready implementation, see the **[local_copilot plugin](https://github.com/microsoft/moodle-local_copilot)**:
- Event observers: [`db/events.php`](https://github.com/microsoft/moodle-local_copilot/blob/main/db/events.php)
- Observer implementation: [`classes/observers.php`](https://github.com/microsoft/moodle-local_copilot/blob/main/classes/observers.php)
- Web services definition: [`db/services.php`](https://github.com/microsoft/moodle-local_copilot/blob/main/db/services.php)

### Flow Diagram

```
User authenticates → OAuth2 access token created
                          ↓
            \local_oauth2\event\access_token_created event triggered
                          ↓
            Your plugin's observer catches the event
                          ↓
            Observer creates/updates entry in external_tokens table
                          ↓
            User can now call your web services with the OAuth2 token
```

### Benefits of This Approach

- Single sign-on: Users authenticate once via OAuth2
- Token synchronization: OAuth2 token lifecycle automatically manages web service token
- Scope-based access: Can map OAuth2 scopes to different web services
- Standard compliance: Supports OAuth2 and OpenID Connect flows
- Secure: Tokens expire automatically when OAuth2 tokens expire

## OpenID Connect interoperability

The plugin can act as an OpenID Connect provider for clients such as oauth2-proxy.

Implemented endpoints:

- Authorization endpoint: `/local/oauth2/login.php`
- Token endpoint: `/local/oauth2/token.php`
- Refresh token endpoint: `/local/oauth2/refresh_token.php`
- UserInfo endpoint: `/local/oauth2/userinfo.php`
- JWKS endpoint: `/local/oauth2/jwks.php`
- Discovery document endpoint: `/local/oauth2/openid_configuration.php`

A default RSA signing key pair for all clients is created automatically when the plugin is installed, so no extra step is needed for a normal installation. Use the CLI script below to create keys for a single client, to create the default keys if they are missing (for example if key generation failed during installation because OpenSSL was unavailable), or to rotate existing keys with `--force`:

```bash
php public/local/oauth2/cli/generate_keys.php [--client-id=<client_id>] [--force]
```

`--client-id` optionally associates the keys with a single client (default: the default keys used for all clients). Without `--force` the script stops with "Keys already exist" if keys are already present for the selected target. With `--force` the existing keys are replaced, so tokens signed with the old keys can no longer be verified against the published JWK Set.

The plugin issues signed `id_token` values for Authorization Code requests that include the `openid` scope. Tokens are signed with `RS256` and include a deterministic `kid` header that matches the published JWK Set.

### Discovery and standard well-known path

Moodle local plugins cannot directly own the site root `/.well-known/openid-configuration` path. The plugin therefore exposes discovery metadata at `/local/oauth2/openid_configuration.php`.

To support standard OIDC discovery for relying parties such as oauth2-proxy, expose the standard path through your web server or reverse proxy and rewrite it to the plugin endpoint.

Example Nginx rewrite:

```nginx
location = /.well-known/openid-configuration {
    rewrite ^ /local/oauth2/openid_configuration.php last;
}
```

If you publish discovery through a reverse proxy, ensure the issuer configured in Moodle matches the public issuer URL exactly.

## Contributors
Apart from people in this repository, the plugin has been created based on the [local_oauth project](https://github.com/projectestac/moodle-local_oauth) with the following contributors:

- [crazyserver](https://github.com/crazyserver)
- [monicagrau](https://github.com/monicagrau)
- [toniginard](https://github.com/toniginard)
- [sarjona](https://github.com/sarjona)
- [lfzawacki](https://github.com/lfzawacki)
- [ignacioabejaro](https://github.com/ignacioabejaro)
- [umerf52](https://github.com/umerf52)

