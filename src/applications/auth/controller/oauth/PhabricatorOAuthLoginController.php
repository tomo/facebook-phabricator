<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class PhabricatorOAuthLoginController extends PhabricatorAuthController {

  private $provider;
  private $userID;

  private $accessToken;
  private $tokenExpires;
  private $oauthState;

  public function shouldRequireLogin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->provider = PhabricatorOAuthProvider::newProvider($data['provider']);
  }

  public function processRequest() {
    $current_user = $this->getRequest()->getUser();

    $provider = $this->provider;
    if (!$provider->isProviderEnabled()) {
      return new Aphront400Response();
    }

    $provider_name = $provider->getProviderName();
    $provider_key = $provider->getProviderKey();

    $request = $this->getRequest();

    if ($request->getStr('error')) {
      $error_view = id(new PhabricatorOAuthFailureView())
        ->setRequest($request);
      return $this->buildErrorResponse($error_view);
    }

    $error_response = $this->retrieveAccessToken($provider);
    if ($error_response) {
      return $error_response;
    }

    $userinfo_uri = new PhutilURI($provider->getUserInfoURI());
    $userinfo_uri->setQueryParams(
      array(
        'access_token' => $this->accessToken,
      ));

    $user_json = @file_get_contents($userinfo_uri);
    $user_data = json_decode($user_json, true);

    $provider->setUserData($user_data);
    $provider->setAccessToken($this->accessToken);

    $user_id = $provider->retrieveUserID();
    $provider_key = $provider->getProviderKey();

    $oauth_info = $this->retrieveOAuthInfo($provider);

    if ($current_user->getPHID()) {
      if ($oauth_info->getID()) {
        if ($oauth_info->getUserID() != $current_user->getID()) {
          $dialog = new AphrontDialogView();
          $dialog->setUser($current_user);
          $dialog->setTitle('Already Linked to Another Account');
          $dialog->appendChild(
            '<p>The '.$provider_name.' account you just authorized '.
            'is already linked to another Phabricator account. Before you can '.
            'associate your '.$provider_name.' account with this Phabriactor '.
            'account, you must unlink it from the Phabricator account it is '.
            'currently linked to.</p>');
          $dialog->addCancelButton('/settings/page/'.$provider_key.'/');

          return id(new AphrontDialogResponse())->setDialog($dialog);
        } else {
          return id(new AphrontRedirectResponse())
            ->setURI('/settings/page/'.$provider_key.'/');
        }
      }

      $existing_oauth = id(new PhabricatorUserOAuthInfo())->loadOneWhere(
        'userID = %d AND oauthProvider = %s',
        $current_user->getID(),
        $provider_key);

      if ($existing_oauth) {
        $dialog = new AphrontDialogView();
        $dialog->setUser($current_user);
        $dialog->setTitle('Already Linked to an Account From This Provider');
        $dialog->appendChild(
          '<p>The account you are logged in with is already linked to a '.
          $provider_name.' account. Before you can link it to a different '.
          $provider_name.' account, you must unlink the old account.</p>');
        $dialog->addCancelButton('/settings/page/'.$provider_key.'/');
        return id(new AphrontDialogResponse())->setDialog($dialog);
      }

      if (!$request->isDialogFormPost()) {
        $dialog = new AphrontDialogView();
        $dialog->setUser($current_user);
        $dialog->setTitle('Link '.$provider_name.' Account');
        $dialog->appendChild(
          '<p>Link your '.$provider_name.' account to your Phabricator '.
          'account?</p>');
        $dialog->addHiddenInput('token', $provider->getAccessToken());
        $dialog->addHiddenInput('expires', $oauth_info->getTokenExpires());
        $dialog->addHiddenInput('state', $this->oauthState);
        $dialog->addSubmitButton('Link Accounts');
        $dialog->addCancelButton('/settings/page/'.$provider_key.'/');

        return id(new AphrontDialogResponse())->setDialog($dialog);
      }

      $oauth_info->setUserID($current_user->getID());
      $oauth_info->save();

      return id(new AphrontRedirectResponse())
        ->setURI('/settings/page/'.$provider_key.'/');
    }

    $next_uri = $request->getCookie('next_uri', '/');

    // Login with known auth.

    if ($oauth_info->getID()) {
      $known_user = id(new PhabricatorUser())->load($oauth_info->getUserID());

      $request->getApplicationConfiguration()->willAuthenticateUserWithOAuth(
        $known_user,
        $oauth_info,
        $provider);

      $session_key = $known_user->establishSession('web');

      $oauth_info->save();

      $request->setCookie('phusr', $known_user->getUsername());
      $request->setCookie('phsid', $session_key);
      $request->clearCookie('next_uri');
      return id(new AphrontRedirectResponse())
        ->setURI($next_uri);
    }

    $oauth_email = $provider->retrieveUserEmail();
    if ($oauth_email) {
      $known_email = id(new PhabricatorUser())
        ->loadOneWhere('email = %s', $oauth_email);
      if ($known_email) {
        $dialog = new AphrontDialogView();
        $dialog->setUser($current_user);
        $dialog->setTitle('Already Linked to Another Account');
        $dialog->appendChild(
          '<p>The '.$provider_name.' account you just authorized has an '.
          'email address which is already in use by another Phabricator '.
          'account. To link the accounts, log in to your Phabricator '.
          'account and then go to Settings.</p>');
        $dialog->addCancelButton('/login/');

        return id(new AphrontDialogResponse())->setDialog($dialog);
      }
    }

    if (!$provider->isProviderRegistrationEnabled()) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($current_user);
      $dialog->setTitle('No Account Registration With '.$provider_name);
      $dialog->appendChild(
        '<p>You can not register a new account using '.$provider_name.'; '.
        'you can only use your '.$provider_name.' account to log into an '.
        'existing Phabricator account which you have registered through '.
        'other means.</p>');
      $dialog->addCancelButton('/login/');

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $class = PhabricatorEnv::getEnvConfig('controller.oauth-registration');
    PhutilSymbolLoader::loadClass($class);
    $controller = newv($class, array($this->getRequest()));

    $controller->setOAuthProvider($provider);
    $controller->setOAuthInfo($oauth_info);
    $controller->setOAuthState($this->oauthState);

    return $this->delegateToController($controller);
  }

  private function buildErrorResponse(PhabricatorOAuthFailureView $view) {
    $provider = $this->provider;

    $provider_name = $provider->getProviderName();
    $view->setOAuthProvider($provider);

    return $this->buildStandardPageResponse(
      $view,
      array(
        'title' => $provider_name.' Auth Failed',
      ));
  }

  private function retrieveAccessToken(PhabricatorOAuthProvider $provider) {
    $request = $this->getRequest();

    $token = $request->getStr('token');
    if ($token) {
      $this->tokenExpires = $request->getInt('expires');
      $this->accessToken = $token;
      $this->oauthState = $request->getStr('state');
      return null;
    }

    $client_id        = $provider->getClientID();
    $client_secret    = $provider->getClientSecret();
    $redirect_uri     = $provider->getRedirectURI();
    $auth_uri         = $provider->getTokenURI();

    $code = $request->getStr('code');
    $query_data = array(
      'client_id'     => $client_id,
      'client_secret' => $client_secret,
      'redirect_uri'  => $redirect_uri,
      'code'          => $code,
    );

    $post_data = http_build_query($query_data);
    $post_length = strlen($post_data);

    $stream_context = stream_context_create(
      array(
        'http' => array(
          'method'  => 'POST',
          'header'  =>
            "Content-Type: application/x-www-form-urlencoded\r\n".
            "Content-Length: {$post_length}\r\n",
          'content' => $post_data,
        ),
      ));

    $stream = fopen($auth_uri, 'r', false, $stream_context);

    $response = false;
    $meta = null;
    if ($stream) {
      $meta = stream_get_meta_data($stream);
      $response = stream_get_contents($stream);
      fclose($stream);
    }

    if ($response === false) {
      return $this->buildErrorResponse(new PhabricatorOAuthFailureView());
    }

    $data = array();
    parse_str($response, $data);

    $token = idx($data, 'access_token');
    if (!$token) {
      return $this->buildErrorResponse(new PhabricatorOAuthFailureView());
    }

    if (idx($data, 'expires')) {
      $this->tokenExpires = time() + $data['expires'];
    }

    $this->accessToken = $token;
    $this->oauthState = $request->getStr('state');

    return null;
  }

  private function retrieveOAuthInfo(PhabricatorOAuthProvider $provider) {

    $oauth_info = id(new PhabricatorUserOAuthInfo())->loadOneWhere(
      'oauthProvider = %s and oauthUID = %s',
      $provider->getProviderKey(),
      $provider->retrieveUserID());

    if (!$oauth_info) {
      $oauth_info = new PhabricatorUserOAuthInfo();
      $oauth_info->setOAuthProvider($provider->getProviderKey());
      $oauth_info->setOAuthUID($provider->retrieveUserID());
    }

    $oauth_info->setAccountURI($provider->retrieveUserAccountURI());
    $oauth_info->setAccountName($provider->retrieveUserAccountName());
    $oauth_info->setToken($provider->getAccessToken());
    $oauth_info->setTokenStatus(PhabricatorUserOAuthInfo::TOKEN_STATUS_GOOD);

    // If we have out-of-date expiration info, just clear it out. Then replace
    // it with good info if the provider gave it to us.
    $expires = $oauth_info->getTokenExpires();
    if ($expires <= time()) {
      $expires = null;
    }
    if ($this->tokenExpires) {
      $expires = $this->tokenExpires;
    }
    $oauth_info->setTokenExpires($expires);

    return $oauth_info;
  }

}
