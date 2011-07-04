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
class ManiphestTaskEditController extends ManiphestController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $files = array();

    if ($this->id) {
      $task = id(new ManiphestTask())->load($this->id);
      if (!$task) {
        return new Aphront404Response();
      }
    } else {
      $task = new ManiphestTask();
      $task->setPriority(ManiphestTaskPriority::PRIORITY_TRIAGE);
      $task->setAuthorPHID($user->getPHID());

      // These allow task creation with defaults.
      if (!$request->isFormPost()) {
        $task->setTitle($request->getStr('title'));
      }

      $file_phids = $request->getArr('files', array());
      if (!$file_phids) {
        // Allow a single 'file' key instead, mostly since Mac OS X urlencodes
        // square brackets in URLs when passed to 'open', so you can't 'open'
        // a URL like '?files[]=xyz' and have PHP interpret it correctly.
        $phid = $request->getStr('file');
        if ($phid) {
          $file_phids = array($phid);
        }
      }

      if ($file_phids) {
        $files = id(new PhabricatorFile())->loadAllWhere(
          'phid IN (%Ls)',
          $file_phids);
      }
    }

    $errors = array();
    $e_title = true;

    if ($request->isFormPost()) {

      $changes = array();

      $new_title = $request->getStr('title');
      $new_desc = $request->getStr('description');

      if ($task->getID()) {
        if ($new_title != $task->getTitle()) {
          $changes[ManiphestTransactionType::TYPE_TITLE] = $new_title;
        }
        if ($new_desc != $task->getDescription()) {
          $changes[ManiphestTransactionType::TYPE_DESCRIPTION] = $new_desc;
        }
      } else {
        $task->setTitle($new_title);
        $task->setDescription($new_desc);
        $changes[ManiphestTransactionType::TYPE_STATUS] =
          ManiphestTaskStatus::STATUS_OPEN;
      }

      $owner_tokenizer = $request->getArr('assigned_to');
      $owner_phid = reset($owner_tokenizer);

      if (!strlen($new_title)) {
        $e_title = 'Required';
        $errors[] = 'Title is required.';
      }

      if (!$errors) {


        if ($request->getInt('priority') != $task->getPriority()) {
          $changes[ManiphestTransactionType::TYPE_PRIORITY] =
            $request->getInt('priority');
        }

        if ($owner_phid != $task->getOwnerPHID()) {
          $changes[ManiphestTransactionType::TYPE_OWNER] = $owner_phid;
        }

        if ($request->getArr('cc') != $task->getCCPHIDs()) {
          $changes[ManiphestTransactionType::TYPE_CCS] = $request->getArr('cc');
        }

        $new_proj_arr = $request->getArr('projects');
        $new_proj_arr = array_values($new_proj_arr);
        sort($new_proj_arr);

        $cur_proj_arr = $task->getProjectPHIDs();
        $cur_proj_arr = array_values($cur_proj_arr);
        sort($cur_proj_arr);

        if ($new_proj_arr != $cur_proj_arr) {
          $changes[ManiphestTransactionType::TYPE_PROJECTS] = $new_proj_arr;
        }

        if ($files) {
          $file_map = mpull($files, 'getPHID');
          $file_map = array_fill_keys($file_map, true);
          $changes[ManiphestTransactionType::TYPE_ATTACH] = array(
            PhabricatorPHIDConstants::PHID_TYPE_FILE => $file_map,
          );
        }

        $template = new ManiphestTransaction();
        $template->setAuthorPHID($user->getPHID());
        $transactions = array();

        foreach ($changes as $type => $value) {
          $transaction = clone $template;
          $transaction->setTransactionType($type);
          $transaction->setNewValue($value);
          $transactions[] = $transaction;
        }

        if ($transactions) {
          $editor = new ManiphestTransactionEditor();
          $editor->applyTransactions($task, $transactions);
        }

        return id(new AphrontRedirectResponse())
          ->setURI('/T'.$task->getID());
      }
    } else {
      if (!$task->getID()) {
        $task->setCCPHIDs(array(
          $user->getPHID(),
        ));
      }
    }

    $phids = array_merge(
      array($task->getOwnerPHID()),
      $task->getCCPHIDs(),
      $task->getProjectPHIDs());
    $phids = array_filter($phids);
    $phids = array_unique($phids);

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles($phids);

    $tvalues = mpull($handles, 'getFullName', 'getPHID');

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setErrors($errors);
      $error_view->setTitle('Form Errors');
    }

    $priority_map = ManiphestTaskPriority::getTaskPriorityMap();

    if ($task->getOwnerPHID()) {
      $assigned_value = array(
        $task->getOwnerPHID() => $handles[$task->getOwnerPHID()]->getFullName(),
      );
    } else {
      $assigned_value = array();
    }

    if ($task->getCCPHIDs()) {
      $cc_value = array_select_keys($tvalues, $task->getCCPHIDs());
    } else {
      $cc_value = array();
    }

    if ($task->getProjectPHIDs()) {
      $projects_value = array_select_keys($tvalues, $task->getProjectPHIDs());
    } else {
      $projects_value = array();
    }

    if ($task->getID()) {
      $cancel_uri = '/T'.$task->getID();
      $button_name = 'Save Task';
      $header_name = 'Edit Task';
    } else {
      $cancel_uri = '/maniphest/';
      $button_name = 'Create Task';
      $header_name = 'Create New Task';
    }

    $project_tokenizer_id = celerity_generate_unique_node_id();

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->setAction($request->getRequestURI()->getPath())
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Title')
          ->setName('title')
          ->setError($e_title)
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
          ->setValue($task->getTitle()))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('Assigned To')
          ->setName('assigned_to')
          ->setValue($assigned_value)
          ->setDatasource('/typeahead/common/users/')
          ->setLimit(1))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('CC')
          ->setName('cc')
          ->setValue($cc_value)
          ->setDatasource('/typeahead/common/mailable/'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Priority')
          ->setName('priority')
          ->setOptions($priority_map)
          ->setValue($task->getPriority()))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('Projects')
          ->setName('projects')
          ->setValue($projects_value)
          ->setID($project_tokenizer_id)
          ->setCaption(
            javelin_render_tag(
              'a',
              array(
                'href'        => '/project/create/',
                'mustcapture' => true,
                'sigil'       => 'project-create',
              ),
              'Create New Project'))
          ->setDatasource('/typeahead/common/projects/'));

    require_celerity_resource('aphront-error-view-css');

    Javelin::initBehavior('maniphest-project-create', array(
      'tokenizerID' => $project_tokenizer_id,
    ));

    if ($files) {
      $file_display = array();
      foreach ($files as $file) {
        $file_display[] = phutil_escape_html($file->getName());
      }
      $file_display = implode('<br />', $file_display);

      $form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Files')
          ->setValue($file_display));

      foreach ($files as $ii => $file) {
        $form->addHiddenInput('files['.$ii.']', $file->getPHID());
      }
    }

    $form
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Description')
          ->setName('description')
          ->setValue($task->getDescription()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue($button_name));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);
    $panel->setHeader($header_name);
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => 'Create Task',
      ));
  }
}
