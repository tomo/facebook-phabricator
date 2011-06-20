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

class PhabricatorProject extends PhabricatorProjectDAO {

  protected $name;
  protected $phid;
  protected $status = PhabricatorProjectStatus::UNKNOWN;
  protected $authorPHID;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_PROJ);
  }

  public function getProfile() {
    $profile = id(new PhabricatorProjectProfile())->loadOneWhere(
      'projectPHID = %s',
      $this->getPHID());
    return $profile;
  }

  public function loadAffiliations() {
    $affiliations = id(new PhabricatorProjectAffiliation())->loadAllWhere(
      'projectPHID = %s ORDER BY IF(status = "former", 1, 0), dateCreated',
      $this->getPHID());
    return $affiliations;
  }
}
