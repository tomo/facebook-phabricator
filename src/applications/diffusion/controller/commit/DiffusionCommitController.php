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

class DiffusionCommitController extends DiffusionController {

  const CHANGES_LIMIT = 100;

  public function processRequest() {
    $drequest = $this->getDiffusionRequest();
    $request = $this->getRequest();
    $user = $request->getUser();

    $callsign = $drequest->getRepository()->getCallsign();

    $content = array();
    $content[] = $this->buildCrumbs(array(
      'commit' => true,
    ));

    $detail_panel = new AphrontPanelView();

    $repository = $drequest->getRepository();
    $commit = $drequest->loadCommit();

    if (!$commit) {
      // TODO: Make more user-friendly.
      throw new Exception('This commit has not parsed yet.');
    }

    $commit_data = $drequest->loadCommitData();

    $factory = new DifferentialMarkupEngineFactory();
    $engine = $factory->newDifferentialCommentMarkupEngine();

    require_celerity_resource('diffusion-commit-view-css');
    require_celerity_resource('phabricator-remarkup-css');

    $property_table = $this->renderPropertyTable($commit, $commit_data);

    $detail_panel->appendChild(
      '<div class="diffusion-commit-view">'.
        '<div class="diffusion-commit-dateline">'.
          'r'.$callsign.$commit->getCommitIdentifier().
          ' &middot; '.
          phabricator_datetime($commit->getEpoch(), $user).
        '</div>'.
        '<h1>Revision Detail</h1>'.
        '<div class="diffusion-commit-details">'.
          $property_table.
          '<hr />'.
          '<div class="diffusion-commit-message phabricator-remarkup">'.
            $engine->markupText($commit_data->getCommitMessage()).
          '</div>'.
        '</div>'.
      '</div>');

    $content[] = $detail_panel;

    $change_query = DiffusionPathChangeQuery::newFromDiffusionRequest(
      $drequest);
    $changes = $change_query->loadChanges();

    $original_changes_count = count($changes);
    if ($request->getStr('show_all') !== 'true' &&
        $original_changes_count > self::CHANGES_LIMIT) {
      $changes = array_slice($changes, 0, self::CHANGES_LIMIT);
    }

    $change_table = new DiffusionCommitChangeTableView();
    $change_table->setDiffusionRequest($drequest);
    $change_table->setPathChanges($changes);

    $count = count($changes);

    $bad_commit = null;
    if ($count == 0) {
      $bad_commit = queryfx_one(
        id(new PhabricatorRepository())->establishConnection('r'),
        'SELECT * FROM %T WHERE fullCommitName = %s',
        PhabricatorRepository::TABLE_BADCOMMIT,
        'r'.$callsign.$commit->getCommitIdentifier());
    }

    if ($bad_commit) {
      $error_panel = new AphrontErrorView();
      $error_panel->setWidth(AphrontErrorView::WIDTH_WIDE);
      $error_panel->setTitle('Bad Commit');
      $error_panel->appendChild(
        phutil_escape_html($bad_commit['description']));

      $content[] = $error_panel;
    } else {
      $change_panel = new AphrontPanelView();
      $change_panel->setHeader("Changes (".number_format($count).")");

      if ($count !== $original_changes_count) {
        $show_all_button = phutil_render_tag(
          'a',
          array(
            'class'   => 'button green',
            'href'    => '?show_all=true',
          ),
          phutil_escape_html('Show All Changes'));
        $warning_view = id(new AphrontErrorView())
          ->setSeverity(AphrontErrorView::SEVERITY_WARNING)
          ->setTitle(sprintf(
                       "Showing only the first %d changes out of %s!",
                       self::CHANGES_LIMIT,
                       number_format($original_changes_count)));

        $change_panel->appendChild($warning_view);
        $change_panel->addButton($show_all_button);
      }

      $change_panel->appendChild($change_table);

      $content[] = $change_panel;

      if ($changes) {
        $changesets = DiffusionPathChange::convertToDifferentialChangesets(
          $changes);

        $vcs = $repository->getVersionControlSystem();
        switch ($vcs) {
          case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
            $vcs_supports_directory_changes = true;
            break;
          case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
            $vcs_supports_directory_changes = false;
            break;
          default:
            throw new Exception("Unknown VCS.");
        }

        $references = array();
        foreach ($changesets as $key => $changeset) {
          $file_type = $changeset->getFileType();
          if ($file_type == DifferentialChangeType::FILE_DIRECTORY) {
            if (!$vcs_supports_directory_changes) {
              unset($changesets[$key]);
              continue;
            }
          }

          $branch = $drequest->getBranchURIComponent(
            $drequest->getBranch());
          $filename = $changeset->getFilename();
          $commit = $drequest->getCommit();
          $reference = "{$branch}{$filename};{$commit}";
          $references[$key] = $reference;
        }

        $change_list = new DifferentialChangesetListView();
        $change_list->setChangesets($changesets);
        $change_list->setRenderingReferences($references);
        $change_list->setRenderURI('/diffusion/'.$callsign.'/diff/');

        // TODO: This is pretty awkward, unify the CSS between Diffusion and
        // Differential better.
        require_celerity_resource('differential-core-view-css');
        $change_list =
          '<div class="differential-primary-pane">'.
            $change_list->render().
          '</div>';
      } else {
        $change_list =
          '<div style="margin: 2em; color: #666; padding: 1em;
            background: #eee;">'.
            '(no changes blah blah)'.
          '</div>';
      }

      $content[] = $change_list;
    }

    return $this->buildStandardPageResponse(
      $content,
      array(
        'title' => 'Diffusion',
      ));
  }

  private function renderPropertyTable(
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data) {

    $phids = array();
    if ($data->getCommitDetail('authorPHID')) {
      $phids[] = $data->getCommitDetail('authorPHID');
    }
    if ($data->getCommitDetail('reviewerPHID')) {
      $phids[] = $data->getCommitDetail('reviewerPHID');
    }
    if ($data->getCommitDetail('differential.revisionPHID')) {
      $phids[] = $data->getCommitDetail('differential.revisionPHID');
    }

    $handles = array();
    if ($phids) {
      $handles = id(new PhabricatorObjectHandleData($phids))
        ->loadHandles();
    }

    $props = array();

    $author_phid = $data->getCommitDetail('authorPHID');
    if ($data->getCommitDetail('authorPHID')) {
      $props['Author'] = $handles[$author_phid]->renderLink();
    } else {
      $props['Author'] = phutil_escape_html($data->getAuthorName());
    }

    $reviewer_phid = $data->getCommitDetail('reviewerPHID');
    $reviewer_name = $data->getCommitDetail('reviewerName');
    if ($reviewer_phid) {
      $props['Reviewer'] = $handles[$reviewer_phid]->renderLink();
    } else if ($reviewer_name) {
      $props['Reviewer'] = phutil_escape_html($reviewer_name);
    }

    $revision_phid = $data->getCommitDetail('differential.revisionPHID');
    if ($revision_phid) {
      $props['Differential Revision'] = $handles[$revision_phid]->renderLink();
    }

    $rows = array();
    foreach ($props as $key => $value) {
      $rows[] =
        '<tr>'.
          '<th>'.$key.':</th>'.
          '<td>'.$value.'</td>'.
        '</tr>';
    }

    return
      '<table class="diffusion-commit-properties">'.
        implode("\n", $rows).
      '</table>';
  }

}
