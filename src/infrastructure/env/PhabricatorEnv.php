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

final class PhabricatorEnv {
  private static $env;

  public static function setEnvConfig(array $config) {
    self::$env = $config;
  }

  public static function getEnvConfig($key, $default = null) {
    return idx(self::$env, $key, $default);
  }

  public static function envConfigExists($key) {
    return array_key_exists($key, self::$env);
  }

  public static function getURI($path) {
    return rtrim(self::getEnvConfig('phabricator.base-uri'), '/').$path;
  }

  public static function getProductionURI($path) {
    $uri = self::getEnvConfig('phabricator.production-uri');
    if (!$uri) {
      $uri = self::getEnvConfig('phabricator.base-uri');
    }
    return rtrim($uri, '/').$path;
  }

  public static function getAllConfigKeys() {
    return self::$env;
  }

  public static function getDoclink($resource) {
    return 'http://phabricator.com/docs/phabricator/'.$resource;
  }

}
