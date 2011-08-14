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

final class DifferentialTitleFieldSpecification
  extends DifferentialFieldSpecification {

  private $title;
  private $error = true;

  public function shouldAppearOnEdit() {
    $this->title = $this->getRevision()->getTitle();
    return true;
  }

  public function setValueFromRequest(AphrontRequest $request) {
    $this->title = $request->getStr('title');
    $this->error = null;
    return $this;
  }

  public function renderEditControl() {
    return id(new AphrontFormTextAreaControl())
      ->setLabel('Title')
      ->setName('title')
      ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
      ->setError($this->error)
      ->setValue($this->title);
  }

  public function willWriteRevision(DifferentialRevisionEditor $editor) {
    $this->getRevision()->setTitle($this->title);
  }

  public function validateField() {
    if (!strlen($this->title)) {
      $this->error = 'Required';
      throw new DifferentialFieldValidationException(
        "You must provide a title.");
    }
  }

}
