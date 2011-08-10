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

final class DifferentialBlameRevisionFieldSpecification
  extends DifferentialFieldSpecification {

  private $value;

  public function getStorageKey() {
    return 'phabricator:blame-revision';
  }

  public function getValueForStorage() {
    return $this->value;
  }

  public function setValueFromStorage($value) {
    $this->value = $value;
    return $this;
  }

  public function shouldAppearOnEdit() {
    return true;
  }

  public function setValueFromRequest(AphrontRequest $request) {
    $this->value = $request->getStr('aux:phabricator:blame-revision');
    return $this;
  }

  public function renderEditControl() {
    return id(new AphrontFormTextControl())
      ->setLabel('Blame Revision')
      ->setCaption('Revision which broke the stuff which this change fixes.')
      ->setName('aux:phabricator:blame-revision')
      ->setValue($this->value);
  }

  public function validateField() {
    return;
  }

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return 'Blame Revision:';
  }

  public function renderValueForRevisionView() {
    if (!$this->value) {
      return null;
    }
    return phutil_escape_html($this->value);
  }

}
