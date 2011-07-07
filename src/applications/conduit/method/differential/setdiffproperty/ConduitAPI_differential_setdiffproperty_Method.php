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
 * @group conduit
 */
class ConduitAPI_differential_setdiffproperty_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Attach properties to Differential diffs.";
  }

  public function defineParamTypes() {
    return array(
      'diff_id' => 'required diff_id',
      'name'    => 'required string',
      'data'    => 'required string',
    );
  }

  public function defineReturnType() {
    return 'void';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_NOT_FOUND' => 'Diff was not found.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $property = new DifferentialDiffProperty();
    $property->setDiffID($request->getValue('diff_id'));
    $property->setName($request->getValue('name'));
    $property->setData(json_decode($request->getValue('data'), true));
    $property->save();
    return;
  }

}
