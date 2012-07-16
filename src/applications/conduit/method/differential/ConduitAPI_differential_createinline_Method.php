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

/**
 * @group conduit
 */
final class ConduitAPI_differential_createinline_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Add an inline comment to a Differential revision.";
  }

  public function defineParamTypes() {
    return array(
      'revisionID' => 'optional revisionid',
      'diffID'     => 'optional diffid',
      'filePath'   => 'required string',
      'isNewFile'  => 'required bool',
      'lineStart'  => 'required int',
      'lineCount'  => 'optional int',
      'message'    => 'required string',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-BAD-REVISION' => 'Bad revision ID.',
      'ERR-BAD-DIFF'     => 'Bad diff ID, or diff does not belong to revision.',
      'ERR-NEED-DIFF'    => 'Neither revision ID nor diff ID was provided.',
      'ERR-NEED-FILE'    => 'A file path was not provided.',
      'ERR-BAD-FILE'     => "Requested file doesn't exist in this revision."
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $rid = $request->getValue('revisionID');
    $did = $request->getValue('diffID');

    if ($rid) {
      // Given both a revision and a diff, check that they match.
      // Given only a revision, find the active diff.
      $revision = id(new DifferentialRevision())->load($rid);
      if (!$revision) {
        throw new ConduitException('ERR-BAD-REVISION');
      }

      if (!$did) { // did not!
        $diff = $revision->loadActiveDiff();
        $did = $diff->getID();
      } else { // did too!
        $diff = id(new DifferentialDiff())->load($did);
        if (!$diff || $diff->getRevisionID() != $rid) {
          throw new ConduitException('ERR-BAD-DIFF');
        }
      }
    } else if ($did) {
      // Given only a diff, find the parent revision.
      $diff = id(new DifferentialDiff())->load($did);
      if (!$diff) {
        throw new ConduitException('ERR-BAD-DIFF');
      }
      $rid = $diff->getRevisionID();
    } else {
      // Given neither, bail.
      throw new ConduitException('ERR-NEED-DIFF');
    }

    $file = $request->getValue('filePath');
    if (!$file) {
      throw new ConduitException('ERR-NEED-FILE');
    }
    $changes = id(new DifferentialChangeset())->loadAllWhere(
      'diffID = %d',
      $did);
    $cid = null;
    foreach ($changes as $id => $change) {
      if ($file == $change->getFilename()) {
        $cid = $id;
      }
    }
    if ($cid == null) {
      throw new ConduitException('ERR-BAD-FILE');
    }

    $inline = id(new DifferentialInlineComment())
      ->setRevisionID($rid)
      ->setChangesetID($cid)
      ->setAuthorPHID($request->getUser()->getPHID())
      ->setContent($request->getValue('message'))
      ->setIsNewFile($request->getValue('isNewFile'))
      ->setLineNumber($request->getValue('lineStart'))
      ->setLineLength($request->getValue('lineCount', 0))
      ->save();

    // Load everything again, just to be safe.
    $changeset = id(new DifferentialChangeset())
      ->load($inline->getChangesetID());
    return array(
      'filePath' => ($inline->getIsNewFile() ?
        $changeset->getFilename() :
        $changeset->getOldFile()),
      'isNewFile' => $inline->getIsNewFile(),
      'lineNumber' => $inline->getLineNumber(),
      'lineLength' => $inline->getLineLength(),
      'diffID' => $changeset->getDiffID(),
      'content' => $inline->getContent(),
    );
  }

}
