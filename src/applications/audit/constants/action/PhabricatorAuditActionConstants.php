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

final class PhabricatorAuditActionConstants {

  const CONCERN = 'concern';
  const ACCEPT = 'accept';
  const COMMENT = 'comment';

  public static function getActionNameMap() {
    static $map = array(
      self::COMMENT => 'Comment',
      self::CONCERN => 'Raise Concern',
      self::ACCEPT  => 'Accept Commit',
    );

    return $map;
  }

  public static function getActionPastTenseVerb($action) {
    static $map = array(
      self::COMMENT => 'commented on',
      self::CONCERN => 'raised a concern with',
      self::ACCEPT  => 'accepted',
    );
    return idx($map, $action, 'updated');
  }

  public static function getStatusNameMap() {
    static $map = array(
      self::CONCERN => PhabricatorAuditStatusConstants::CONCERNED,
      self::ACCEPT => PhabricatorAuditStatusConstants::ACCEPTED,
    );

    return $map;
  }

}
