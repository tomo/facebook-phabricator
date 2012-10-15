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
 * @group phame
 */
final class PhameDraftListController
  extends PhamePostListBaseController {

  public function shouldRequireLogin() {
    return true;
  }

  protected function getSideNavFilter() {
    return 'draft';
  }

  protected function isDraft() {
    return true;
  }

  protected function getNoticeView() {
    $request = $this->getRequest();

    if ($request->getExists('deleted')) {
      $notice_view = $this->buildNoticeView()
        ->appendChild('Deleted draft successfully.');
    } else {
      $notice_view = null;
    }

    return $notice_view;
  }

  public function processRequest() {
    $user = $this->getRequest()->getUser();
    $phid = $user->getPHID();

    $query = id(new PhamePostQuery())
      ->setViewer($user)
      ->withBloggerPHIDs(array($phid))
      ->withVisibility(PhamePost::VISIBILITY_DRAFT);

    $this->setPhamePostQuery($query);

    $actions = array('view', 'edit');
    $this->setActions($actions);

    $this->setPageTitle('My Drafts');

    $this->setShowSideNav(true);

    return $this->buildPostListPageResponse();
  }
}
