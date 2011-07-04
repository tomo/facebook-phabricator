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
 * @group maniphest
 */
class ManiphestTransaction extends ManiphestDAO {

  protected $taskID;
  protected $authorPHID;
  protected $transactionType;
  protected $oldValue;
  protected $newValue;
  protected $comments;
  protected $cache;
  protected $metadata = array();

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'oldValue' => self::SERIALIZATION_JSON,
        'newValue' => self::SERIALIZATION_JSON,
        'metadata' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function extractPHIDs() {
    $phids = array();

    switch ($this->getTransactionType()) {
      case ManiphestTransactionType::TYPE_CCS:
      case ManiphestTransactionType::TYPE_PROJECTS:
        foreach ($this->getOldValue() as $phid) {
          $phids[] = $phid;
        }
        foreach ($this->getNewValue() as $phid) {
          $phids[] = $phid;
        }
        break;
      case ManiphestTransactionType::TYPE_OWNER:
        $phids[] = $this->getOldValue();
        $phids[] = $this->getNewValue();
        break;
      case ManiphestTransactionType::TYPE_ATTACH:
        $old = $this->getOldValue();
        $new = $this->getNewValue();
        if (!is_array($old)) {
          $old = array();
        }
        if (!is_array($new)) {
          $new = array();
        }
        $val = array_merge(array_values($old), array_values($new));
        foreach ($val as $stuff) {
          foreach ($stuff as $phid => $ignored) {
            $phids[] = $phid;
          }
        }
        break;
    }

    $phids[] = $this->getAuthorPHID();

    return $phids;
  }

  public function canGroupWith($target) {
    if ($target->getAuthorPHID() != $this->getAuthorPHID()) {
      return false;
    }
    if ($target->hasComments() && $this->hasComments()) {
      return false;
    }
    $ttime = $target->getDateCreated();
    $stime = $this->getDateCreated();
    if (abs($stime - $ttime) > 60) {
      return false;
    }

    if ($target->getTransactionType() == $this->getTransactionType()) {
      return false;
    }

    return true;
  }

  public function hasComments() {
    return (bool)strlen(trim($this->getComments()));
  }


}
