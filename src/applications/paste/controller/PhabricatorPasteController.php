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

abstract class PhabricatorPasteController extends PhabricatorController {

  public function buildSideNavView(PhabricatorPaste $paste = null) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI('filter/')));

    if ($paste) {
      $nav->addFilter('paste', 'P'.$paste->getID(), '/P'.$paste->getID());
      $nav->addSpacer();
    }

    $nav->addLabel('Create');
    $nav->addFilter('create', 'New Paste');

    $nav->addSpacer();
    $nav->addLabel('Pastes');
    $nav->addFilter('my', 'My Pastes');
    $nav->addFilter('all', 'All Pastes');

    return $nav;
  }

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName('Paste');
    $page->setBaseURI('/paste/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x9C\x8E");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());

  }
}
