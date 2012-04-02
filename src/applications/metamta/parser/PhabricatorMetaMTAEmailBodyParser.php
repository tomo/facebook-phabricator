<?php

/*
 * Copyright 2012 Facebook, Inc.
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

final class PhabricatorMetaMTAEmailBodyParser {

  public function __construct($corpus) {
    $this->corpus = $corpus;
  }

  public function stripQuotedText() {
    $body = $this->corpus;

    $body = preg_replace(
      '/^\s*On\b.*\bwrote:.*?/msU',
      '',
      $body);

    // Outlook english
    $body = preg_replace(
      '/^\s*-----Original Message-----.*?/msU',
      '',
      $body);

    // Outlook danish
    $body = preg_replace(
      '/^\s*-----Oprindelig Meddelelse-----.*?/msU',
      '',
      $body);

    // HTC Mail application (mobile)
    $body = preg_replace(
      '/^\s*Sent from my HTC smartphone.*?/msU',
      '',
      $body);

    return rtrim($body);
  }

}
