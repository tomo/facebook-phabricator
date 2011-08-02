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


/**
 * @group aphront
 */
class AphrontRequest {

  const TYPE_AJAX = '__ajax__';
  const TYPE_FORM = '__form__';

  private $host;
  private $path;
  private $requestData;
  private $user;
  private $env;
  private $applicationConfiguration;

  final public function __construct($host, $path) {
    $this->host = $host;
    $this->path = $path;
  }

  final public function setApplicationConfiguration(
    $application_configuration) {
    $this->applicationConfiguration = $application_configuration;
    return $this;
  }

  final public function getApplicationConfiguration() {
    return $this->applicationConfiguration;
  }

  final public function setRequestData(array $request_data) {
    $this->requestData = $request_data;
    return $this;
  }

  final public function getPath() {
    return $this->path;
  }

  final public function getHost() {
    return $this->host;
  }

  final public function getInt($name, $default = null) {
    if (isset($this->requestData[$name])) {
      return (int)$this->requestData[$name];
    } else {
      return $default;
    }
  }

  final public function getBool($name, $default = null) {
    if (isset($this->requestData[$name])) {
      if ($this->requestData[$name] === 'true') {
        return true;
      } else if ($this->requestData[$name] === 'false') {
        return false;
      } else {
        return (bool)$this->requestData[$name];
      }
    } else {
      return $default;
    }
  }

  final public function getStr($name, $default = null) {
    if (isset($this->requestData[$name])) {
      $str = (string)$this->requestData[$name];
      // Normalize newline craziness.
      $str = str_replace(
        array("\r\n", "\r"),
        array("\n", "\n"),
        $str);
      return $str;
    } else {
      return $default;
    }
  }

  final public function getArr($name, $default = array()) {
    if (isset($this->requestData[$name]) &&
        is_array($this->requestData[$name])) {
      return $this->requestData[$name];
    } else {
      return $default;
    }
  }

  final public function getExists($name) {
    return array_key_exists($name, $this->requestData);
  }

  final public function isHTTPPost() {
    return ($_SERVER['REQUEST_METHOD'] == 'POST');
  }

  final public function isAjax() {
    return $this->getExists(self::TYPE_AJAX);
  }

  public static function getCSRFTokenName() {
    return '__csrf__';
  }

  public static function getCSRFHeaderName() {
    return 'X-Phabricator-Csrf';
  }

  final public function validateCSRF() {
    $token_name = self::getCSRFTokenName();
    $token = $this->getStr($token_name);

    // No token in the request, check the HTTP header which is added for Ajax
    // requests.
    if (empty($token)) {

      // PHP mangles HTTP headers by uppercasing them and replacing hyphens with
      // underscores, then prepending 'HTTP_'.
      $php_index = self::getCSRFHeaderName();
      $php_index = strtoupper($php_index);
      $php_index = str_replace('-', '_', $php_index);
      $php_index = 'HTTP_'.$php_index;

      $token = idx($_SERVER, $php_index);
    }

    $valid = $this->getUser()->validateCSRFToken($token);
    if (!$valid) {
      // This should only be able to happen if you load a form, pull your
      // internet for 6 hours, and then reconnect and immediately submit,
      // but give the user some indication of what happened since the workflow
      // is incredibly confusing otherwise.
      throw new AphrontCSRFException(
        "The form you just submitted did not include a valid CSRF token. ".
        "This token is a technical security measure which prevents a ".
        "certain type of login hijacking attack. However, the token can ".
        "become invalid if you leave a page open for more than six hours ".
        "without a connection to the internet. To fix this problem: reload ".
        "the page, and then resubmit it.");
    }

    return true;
  }

  final public function isFormPost() {
    $post = $this->getExists(self::TYPE_FORM) &&
            $this->isHTTPPost();

    if (!$post) {
      return false;
    }

    return $this->validateCSRF();
  }

  final public function getCookie($name, $default = null) {
    return idx($_COOKIE, $name, $default);
  }

  final public function clearCookie($name) {
    $this->setCookie($name, '', time() - (60 * 60 * 24 * 30));
  }

  final public function setCookie($name, $value, $expire = null) {
    if ($expire === null) {
      $expire = time() + (60 * 60 * 24 * 365 * 5);
    }
    setcookie(
      $name,
      $value,
      $expire,
      $path = '/',
      $domain = '',
      $secure = false,
      $http_only = true);
  }

  final public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  final public function getUser() {
    return $this->user;
  }

  final public function getRequestURI() {
    $get = $_GET;
    unset($get['__path__']);
    return id(new PhutilURI($this->getPath()))->setQueryParams($get);
  }

  final public function isDialogFormPost() {
    return $this->isFormPost() && $this->getStr('__dialog__');
  }

}
