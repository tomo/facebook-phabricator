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
class ConduitAPI_differential_updaterevision_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Update a Differential revision.";
  }

  public function defineParamTypes() {
    return array(
      'id'        => 'required revisionid',
      'diffid'    => 'required diffid',
      'fields'    => 'required dict',
      'message'   => 'required string',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_BAD_DIFF' => 'Bad diff ID.',
      'ERR_BAD_REVISION' => 'Bad revision ID.',
      'ERR_WRONG_USER' => 'You are not the author of this revision.',
      'ERR_COMMITTED' => 'This revision has already been committed.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $diff = id(new DifferentialDiff())->load($request->getValue('diffid'));
    if (!$diff) {
      throw new ConduitException('ERR_BAD_DIFF');
    }

    $revision = id(new DifferentialRevision())->load($request->getValue('id'));

    if ($request->getUser()->getPHID() !== $revision->getAuthorPHID()) {
      throw new ConduitException('ERR_WRONG_USER');
    }

    if ($revision->getStatus() == DifferentialRevisionStatus::COMMITTED) {
      throw new ConduitException('ERR_COMMITTED');
    }

    $editor = new DifferentialRevisionEditor(
      $revision,
      $revision->getAuthorPHID());
    $fields = $request->getValue('fields');
    $editor->copyFieldsFromConduit($fields);

    $editor->addDiff($diff, $request->getValue('message'));
    $editor->save();

    return array(
      'revisionid'  => $revision->getID(),
      'uri'         => PhabricatorEnv::getURI('/D'.$revision->getID()),
    );
  }

}
