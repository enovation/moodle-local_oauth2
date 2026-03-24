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
 * OIDC authorize controller that enriches auth-code ID tokens with scoped claims.
 *
 * @package    local_oauth2
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_oauth2\controller;

use OAuth2\OpenID\Controller\AuthorizeController as openid_authorize_controller;
use OAuth2\OpenID\Storage\UserClaimsInterface;
use OAuth2\ScopeInterface;
use OAuth2\Storage\ClientInterface;

/**
 * OIDC authorize controller that includes profile/email claims in auth-code ID tokens.
 */
class authorize_controller extends openid_authorize_controller {
    /**
     * @var UserClaimsInterface
     */
    protected $userclaimsstorage;

    /**
     * Constructor.
     *
     * @param ClientInterface $clientstorage
     * @param array $responseTypes
     * @param array $config
     * @param ScopeInterface|null $scopeUtil
     * @param UserClaimsInterface $userclaimsstorage
     */
    public function __construct(
        ClientInterface $clientstorage,
        array $responseTypes,
        array $config,
        ?ScopeInterface $scopeUtil,
        UserClaimsInterface $userclaimsstorage
    ) {
        parent::__construct($clientstorage, $responseTypes, $config, $scopeUtil);
        $this->userclaimsstorage = $userclaimsstorage;
    }

    /**
     * Build authorize parameters and include user claims in auth-code ID tokens.
     *
     * @param \OAuth2\RequestInterface $request
     * @param \OAuth2\ResponseInterface $response
     * @param mixed $user_id
     * @return array|null
     */
    protected function buildAuthorizeParameters($request, $response, $user_id) {
        $params = parent::buildAuthorizeParameters($request, $response, $user_id);
        if (!$params) {
            return null;
        }

        if ($this->needsIdToken($this->getScope())
            && $this->getResponseType() == self::RESPONSE_TYPE_AUTHORIZATION_CODE) {
            $claims = $this->userclaimsstorage->getUserClaims($user_id, $this->getScope());
            $params['id_token'] = $this->responseTypes['id_token']->createIdToken(
                $this->getClientId(),
                $user_id,
                $this->getNonce(),
                $claims
            );
        }

        return $params;
    }

    /**
     * Validate authorize requests and require state for OIDC requests.
     *
     * Enforcing state for OpenID Connect code flows avoids silent consented
     * logins being triggered cross-site for clients that rely on this endpoint
     * for authentication.
     *
     * @param \OAuth2\RequestInterface $request
     * @param \OAuth2\ResponseInterface $response
     * @return bool
     */
    public function validateAuthorizeRequest($request, $response) {
        if (!parent::validateAuthorizeRequest($request, $response)) {
            return false;
        }

        if (!$this->needsIdToken($this->getScope()) || $this->getState()) {
            return true;
        }

        $redirecturi = $this->getRedirectUri();
        if (empty($redirecturi)) {
            $clientdetails = $this->clientStorage->getClientDetails($this->getClientId());
            $redirecturi = $clientdetails['redirect_uri'] ?? null;
        }

        if ($redirecturi) {
            $response->setRedirect(
                $this->config['redirect_status_code'],
                $redirecturi,
                null,
                'invalid_request',
                'The state parameter is required for OpenID Connect requests'
            );
        } else {
            $response->setError(400, 'invalid_request', 'The state parameter is required for OpenID Connect requests');
        }

        return false;
    }
}