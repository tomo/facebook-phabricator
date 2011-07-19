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
  protected $subprojectPHIDs = array();

  private $subprojectsNeedUpdate;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'subprojectPHIDs' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_PROJ);
  }

  public function setSubprojectPHIDs(array $phids) {
    $this->subprojectPHIDs = $phids;
    $this->subprojectsNeedUpdate = true;
    return $this;
  }

  public function loadProfile() {
    $profile = id(new PhabricatorProjectProfile())->loadOneWhere(
      'projectPHID = %s',
      $this->getPHID());
    return $profile;
  }

  public function loadAffiliations() {
    $affils = PhabricatorProjectAffiliation::loadAllForProjectPHIDs(
      array($this->getPHID()));
    return $affils[$this->getPHID()];
  }

  public function save() {
    $result = parent::save();

    if ($this->subprojectsNeedUpdate) {
      // If we've changed the project PHIDs for this task, update the link
      // table.
      PhabricatorProjectSubproject::updateProjectSubproject($this);
      $this->subprojectsNeedUpdate = false;
    }

    return $result;
  }

}
