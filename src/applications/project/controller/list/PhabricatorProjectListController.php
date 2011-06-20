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

class PhabricatorProjectListController
  extends PhabricatorProjectController {

  public function processRequest() {

    $projects = id(new PhabricatorProject())->loadAllWhere(
      '1 = 1 ORDER BY id DESC limit 100');

    $author_phids = mpull($projects, 'getAuthorPHID');
    $handles = id(new PhabricatorObjectHandleData($author_phids))
      ->loadHandles();

    $rows = array();
    foreach ($projects as $project) {
      $documents = new PhabricatorProjectTransactionSearch($project->getPHID());
      // search all open documents by default
      $documents->setSearchOptions();
      $documents = $documents->executeSearch();

      $documents_types = igroup($documents,'documentType');
      $tasks = idx(
        $documents_types,
        PhabricatorPHIDConstants::PHID_TYPE_TASK);
      $tasks_amount = count($tasks);

      // TODO: set up a relationship between the project and the arcanist's
      //       project, to be able get the revisions.
      $revisions = idx(
        $documents_types,
        PhabricatorPHIDConstants::PHID_TYPE_DREV);
      $revisions_amount = count($revisions);

      $profile = $project->getProfile();
      $affiliations = $project->loadAffiliations();
      $population = count($affiliations);

      $status = PhabricatorProjectStatus::getNameForStatus(
        $project->getStatus());

      $blurb = nonempty(
        $profile->getBlurb(),
        'Oops!, nothing is known about this elusive project.');
      $blurb = $this->textWrap($blurb, $columns = 100);

      $rows[] = array(
        phutil_escape_html($project->getName()),
        phutil_escape_html($blurb),
        $handles[$project->getAuthorPHID()]->renderLink(),
        phutil_escape_html($population),
        phutil_escape_html($status),
        phutil_escape_html($tasks_amount),
        //  phutil_escape_html($revisions_amount),
        phutil_render_tag(
          'a',
          array(
            'class' => 'small grey button',
            'href' => '/project/view/'.$project->getID().'/',
          ),
          'View Project Profile'),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Project',
        'Blurb',
        'Mastermind',
        'Population',
        'Status',
        'Open Tasks',
        // 'Open Revisions',
        '',
      ));
    $table->setColumnClasses(
      array(
        'pri',
        'wide',
        '',
        'right',
        'pri',
        'right',
        // 'right',
        'action',
      ));

    $panel = new AphrontPanelView();
    $panel->appendChild($table);
    $panel->setHeader('Project');
    $panel->setCreateButton('Create New Project', '/project/edit/');

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Projects',
      ));
  }

  private function textWrap($text, $length) {
    if (strlen($text) <= $length) {
      return $text;
    } else {
      // TODO:  perhaps this could be improved, adding the ability to get the
      //        last letter and suppress it, if it is one of [(,:; ,etc.
      //        making "blurb" looks a little bit better. :)
      $wrapped = wordwrap($text, $length, '__#END#__');
      $end_position = strpos($wrapped, '__#END#__');
      $wrapped = substr($text, 0, $end_position).'...';
      return $wrapped;
    }
  }
}
