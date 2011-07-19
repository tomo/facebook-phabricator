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

class PhabricatorProjectProfileController
  extends PhabricatorProjectController {

  private $id;
  private $page;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
    $this->page = idx($data, 'page');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $uri = $request->getRequestURI();

    $project = id(new PhabricatorProject())->load($this->id);
    if (!$project) {
      return new Aphront404Response();
    }
    $profile = $project->loadProfile();
    if (!$profile) {
      $profile = new PhabricatorProjectProfile();
    }

    $src_phid = $profile->getProfileImagePHID();
    if (!$src_phid) {
      $src_phid = $user->getProfileImagePHID();
    }
    $picture = PhabricatorFileURI::getViewURIForPHID($src_phid);

    $pages = array(
      /*
      '<h2>Active Documents</h2>',
      'tasks'        => 'Maniphest Tasks',
      'revisions'    => 'Differential Revisions',
      '<hr />',
      '<h2>Workflow</h2>',
      'goals'        => 'Goals',
      'statistics'   => 'Statistics',
      '<hr />', */
      '<h2>Information</h2>',
      'edit'         => 'Edit Project',
      'affiliation'  => 'Edit Affiliation',
    );

    if (empty($pages[$this->page])) {
      $this->page = 'action';
    }

    switch ($this->page) {
      default:
        $content = $this->renderBasicInformation($project, $profile);
        break;
    }

    $profile = new PhabricatorProfileView();
    $profile->setProfilePicture($picture);
    $profile->setProfileNames($project->getName());
    foreach ($pages as $page => $name) {
      if (is_integer($page)) {
        $profile->addProfileItem(
          phutil_render_tag(
            'span',
            array(),
            $name));
      } else {
        $uri->setPath('/project/'.$page.'/'.$project->getID().'/');
        $profile->addProfileItem(
          phutil_render_tag(
            'a',
            array(
              'href' => $uri,
              'class' => ($this->page == $page)
                ? 'phabricator-profile-item-selected'
                : null,
            ),
            phutil_escape_html($name)));
      }
    }

    $profile->appendChild($content);

    return $this->buildStandardPageResponse(
      $profile,
      array(
        'title' => $project->getName(),
        ));
  }

  //----------------------------------------------------------------------------
  // Helper functions

  private function renderBasicInformation($project, $profile) {
    $blurb = nonempty(
       $profile->getBlurb(),
       '//Nothing is known about this elusive project.//');

    $engine = PhabricatorMarkupEngine::newProfileMarkupEngine();
    $blurb = $engine->markupText($blurb);

    $affiliations = $project->loadAffiliations();

    $phids = array_merge(
      array($project->getAuthorPHID()),
      $project->getSubprojectPHIDs(),
      mpull($affiliations, 'getUserPHID')
    );
    $phids = array_unique($phids);
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    $affiliated = array();
    foreach ($affiliations as $affiliation) {
      $user = $handles[$affiliation->getUserPHID()]->renderLink();
      $role = phutil_escape_html($affiliation->getRole());

      $status = null;
      if ($affiliation->getStatus() == 'former') {
        $role = '<em>Former '.$role.'</em>';
      }

      $affiliated[] = '<li>'.$user.' &mdash; '.$role.$status.'</li>';
    }

    if ($affiliated) {
      $affiliated = '<ul>'.implode("\n", $affiliated).'</ul>';
    } else {
      $affiliated = '<p><em>No one is affiliated with this project.</em></p>';
    }

    if ($project->getSubprojectPHIDs()) {
      $table = $this->renderSubprojectTable(
        $handles,
        $project->getSubprojectPHIDs());
      $subproject_list = $table->render();
    } else {
      $subproject_list =
        '<p><em>There are no projects attached for such specie.</em></p>';
    }


    $timestamp = phabricator_format_timestamp($project->getDateCreated());
    $status = PhabricatorProjectStatus::getNameForStatus(
      $project->getStatus());

    $content =
      '<div class="phabricator-profile-info-group">
        <h1 class="phabricator-profile-info-header">Basic Information</h1>
        <div class="phabricator-profile-info-pane">
          <table class="phabricator-profile-info-table">
            <tr>
              <th>Creator</th>
              <td>'.$handles[$project->getAuthorPHID()]->renderLink().'</td>
            </tr>
            <tr>
              <th>Status</th>
              <td><strong>'.phutil_escape_html($status).'</strong></td>
            </tr>
            <tr>
              <th>Created</th>
              <td>'.$timestamp.'</td>
            </tr>
            <tr>
              <th>PHID</th>
              <td>'.phutil_escape_html($project->getPHID()).'</td>
            </tr>
            <tr>
              <th>Blurb</th>
              <td>'.$blurb.'</td>
            </tr>
          </table>
        </div>
      </div>';

    $content .=
      '<div class="phabricator-profile-info-group">'.
        '<h1 class="phabricator-profile-info-header">Resources</h1>'.
        '<div class="phabricator-profile-info-pane">'.
         $affiliated.
        '</div>'.
      '</div>';

    $content .= '<div class="phabricator-profile-info-group">'.
      '<h1 class="phabricator-profile-info-header">Subprojects</h1>'.
      '<div class="phabricator-profile-info-pane">'.
        $subproject_list.
        '</div>'.
      '</div>';

    $query = id(new ManiphestTaskQuery())
      ->withProjects(array($project->getPHID()))
      ->withStatus(ManiphestTaskQuery::STATUS_OPEN)
      ->setOrderBy(ManiphestTaskQuery::ORDER_PRIORITY)
      ->setLimit(10)
      ->setCalculateRows(true);
    $tasks = $query->execute();
    $count = $query->getRowCount();

    $phids = mpull($tasks, 'getOwnerPHID');
    $phids = array_filter($phids);
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    $task_views = array();
    foreach ($tasks as $task) {
      $view = id(new ManiphestTaskSummaryView())
        ->setTask($task)
        ->setHandles($handles)
        ->setUser($this->getRequest()->getUser());
      $task_views[] = $view->render();
    }

    if (empty($tasks)) {
      $task_views = '<em>No open tasks.</em>';
    } else {
      $task_views = implode('', $task_views);
    }

    $open = number_format($count);

    $more_link = phutil_render_tag(
      'a',
      array(
        'href' => '/maniphest/view/all/?projects='.$project->getPHID(),
      ),
      "View All Open Tasks \xC2\xBB");

    $content .=
      '<div class="phabricator-profile-info-group">
        <h1 class="phabricator-profile-info-header">'.
          "Open Tasks ({$open})".
        '</h1>'.
        '<div class="phabricator-profile-info-pane">'.
          $task_views.
          '<div class="phabricator-profile-info-pane-more-link">'.
            $more_link.
          '</div>'.
        '</div>
      </div>';

    return $content;
  }

  private function renderSubprojectTable(
    PhabricatorObjectHandleData $handles,
    $subprojects_phids) {

    $rows = array();
    foreach ($subprojects_phids as $subproject_phid) {
      $phid = $handles[$subproject_phid]->getPHID();

      $rows[] = array(
        phutil_escape_html($handles[$phid]->getFullName()),
        phutil_render_tag(
          'a',
          array(
            'class' => 'small grey button',
            'href' => $handles[$phid]->getURI(),
          ),
          'View Project Profile'),
      );
    }

    $table = new AphrontTableView($rows);
     $table->setHeaders(
       array(
         'Name',
         '',
       ));
     $table->setColumnClasses(
       array(
         'pri',
         'action right',
       ));

    return $table;
  }
}
