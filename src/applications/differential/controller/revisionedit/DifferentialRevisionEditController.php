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

class DifferentialRevisionEditController extends DifferentialController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();

    if (!$this->id) {
      $this->id = $request->getInt('revisionID');
    }

    if ($this->id) {
      $revision = id(new DifferentialRevision())->load($this->id);
      if (!$revision) {
        return new Aphront404Response();
      }
    } else {
      $revision = new DifferentialRevision();
    }

    $diff_id = $request->getInt('diffID');
    if ($diff_id) {
      $diff = id(new DifferentialDiff())->load($diff_id);
      if (!$diff) {
        return new Aphront404Response();
      }
      if ($diff->getRevisionID()) {
        // TODO: Redirect?
        throw new Exception("This diff is already attached to a revision!");
      }
    } else {
      $diff = null;
    }

    $e_title = true;
    $e_testplan = true;
    $e_reviewers = null;
    $errors = array();

    $revision->loadRelationships();

    if ($request->isFormPost() && !$request->getStr('viaDiffView')) {
      $revision->setTitle($request->getStr('title'));
      $revision->setSummary($request->getStr('summary'));
      $revision->setTestPlan($request->getStr('testplan'));
      $revision->setBlameRevision($request->getStr('blame'));
      $revision->setRevertPlan($request->getStr('revert'));

      if (!strlen(trim($revision->getTitle()))) {
        $errors[] = 'You must provide a title.';
        $e_title = 'Required';
      } else {
        $e_title = null;
      }

      if (!strlen(trim($revision->getTestPlan()))) {
        $errors[] = 'You must provide a test plan.';
        $e_testplan = 'Required';
      } else {
        $e_testplan = null;
      }

      $user_phid = $request->getUser()->getPHID();

      if (in_array($user_phid, $request->getArr('reviewers'))) {
        $errors[] = 'You may not review your own revision.';
        $e_reviewers = 'Invalid';
      }

      if (!$errors) {
        $editor = new DifferentialRevisionEditor($revision, $user_phid);
        if ($diff) {
          $editor->addDiff($diff, $request->getStr('comments'));
        }
        $editor->setCCPHIDs($request->getArr('cc'));
        $editor->setReviewers($request->getArr('reviewers'));
        $editor->save();

        return id(new AphrontRedirectResponse())
          ->setURI('/D'.$revision->getID());
      }

      $reviewer_phids = $request->getArr('reviewers');
      $cc_phids = $request->getArr('cc');
    } else {
      $reviewer_phids = $revision->getReviewers();
      $cc_phids = $revision->getCCPHIDs();
    }

    $phids = array_merge($reviewer_phids, $cc_phids);
    $phids = array_unique($phids);

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();
    $handles = mpull($handles, 'getFullName', 'getPHID');

    $reviewer_map = array_select_keys($handles, $reviewer_phids);
    $cc_map = array_select_keys($handles, $cc_phids);

    $form = new AphrontFormView();
    $form->setUser($request->getUser());
    if ($diff) {
      $form->addHiddenInput('diffID', $diff->getID());
    }

    if ($revision->getID()) {
      $form->setAction('/differential/revision/edit/'.$revision->getID().'/');
    } else {
      $form->setAction('/differential/revision/edit/');
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    }

    if ($diff && $revision->getID()) {
      $form
        ->appendChild(
          id(new AphrontFormTextAreaControl())
            ->setLabel('Comments')
            ->setName('comments')
            ->setCaption("Explain what's new in this diff.")
            ->setValue($request->getStr('comments')))
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue('Save'))
        ->appendChild(
          id(new AphrontFormDividerControl()));
    }

    $form
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Title')
          ->setName('title')
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
          ->setValue($revision->getTitle())
          ->setError($e_title))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Summary')
          ->setName('summary')
          ->setValue($revision->getSummary()))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Test Plan')
          ->setName('testplan')
          ->setValue($revision->getTestPlan())
          ->setError($e_testplan))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('Reviewers')
          ->setName('reviewers')
          ->setDatasource('/typeahead/common/users/')
          ->setError($e_reviewers)
          ->setValue($reviewer_map))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('CC')
          ->setName('cc')
          ->setDatasource('/typeahead/common/mailable/')
          ->setValue($cc_map))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Blame Revision')
          ->setName('blame')
          ->setValue($revision->getBlameRevision())
          ->setCaption('Revision which broke the stuff which this '.
                       'change fixes.'))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Revert Plan')
          ->setName('revert')
          ->setValue($revision->getRevertPlan())
          ->setCaption('Special steps required to safely revert this change.'));

    $submit = id(new AphrontFormSubmitControl())
      ->setValue('Save');
    if ($diff) {
      $submit->addCancelButton('/differential/diff/'.$diff->getID().'/');
    } else {
      $submit->addCancelButton('/D'.$revision->getID());
    }

    $form->appendChild($submit);

    $panel = new AphrontPanelView();
    if ($revision->getID()) {
      if ($diff) {
        $panel->setHeader('Update Differential Revision');
      } else {
        $panel->setHeader('Edit Differential Revision');
      }
    } else {
      $panel->setHeader('Create New Differential Revision');
    }

    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    return $this->buildStandardPageResponse(
      array($error_view, $panel),
      array(
        'title' => 'Edit Differential Revision',
      ));
  }

}
