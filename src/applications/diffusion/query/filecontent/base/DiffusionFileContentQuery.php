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

abstract class DiffusionFileContentQuery extends DiffusionQuery {

  private $needsBlame;
  private $fileContent;

  final public static function newFromDiffusionRequest(
    DiffusionRequest $request) {
    return parent::newQueryObject(__CLASS__, $request);
  }

  public function getSupportsBlameOnBlame() {
    return false;
  }

  public function getPrevRev($rev) {
    // TODO: support git once the 'parent' info of a commit is saved
    // to the database.
    throw new Exception("Unsupported VCS!");
  }

  final public function loadFileContent() {
    $this->fileContent = $this->executeQuery();
  }

  final public function getRawData() {
    return $this->fileContent->getCorpus();
  }

  final public function getBlameData() {
    $raw_data = $this->getRawData();

    $text_list = array();
    $rev_list = array();
    $blame_dict = array();

    if (!$this->getNeedsBlame()) {
      $text_list = explode("\n", rtrim($raw_data));
    } else {
      foreach (explode("\n", rtrim($raw_data)) as $k => $line) {
        list($rev_id, $author, $text) = $this->tokenizeLine($line);

        $text_list[$k] = $text;
        $rev_list[$k] = $rev_id;

        if (!isset($blame_dict[$rev_id]) &&
            !isset($blame_dict[$rev_id]['author'] )) {
          $blame_dict[$rev_id]['author'] = $author;
        }
      }

      $repository = $this->getRequest()->getRepository();

      $commits = id(new PhabricatorRepositoryCommit())->loadAllWhere(
        'repositoryID = %d AND commitIdentifier IN (%Ls)', $repository->getID(),
        array_unique($rev_list));

      foreach ($commits as $commit) {
        $blame_dict[$commit->getCommitIdentifier()]['epoch'] =
          $commit->getEpoch();
      }

      $commits_data = array();
      if ($commits) {
        $commits_data = id(new PhabricatorRepositoryCommitData())->loadAllWhere(
          'commitID IN (%Ls)',
          mpull($commits, 'getID'));
      }

      $phids = array();
      foreach ($commits_data as $data) {
        $phids[] = $data->getCommitDetail('authorPHID');
      }

      $handles = id(new PhabricatorObjectHandleData(array_unique($phids)))
        ->loadHandles();

      foreach ($commits_data as $data) {
        if ($data->getCommitDetail('authorPHID')) {
          $commit_identifier =
            $commits[$data->getCommitID()]->getCommitIdentifier();
          $blame_dict[$commit_identifier]['handle'] =
            $handles[$data->getCommitDetail('authorPHID')];
        }
      }
   }

    return array($text_list, $rev_list, $blame_dict);
  }

  abstract protected function tokenizeLine($line);

  public function setNeedsBlame($needs_blame) {
    $this->needsBlame = $needs_blame;
  }

  public function getNeedsBlame() {
    return $this->needsBlame;
  }
}
