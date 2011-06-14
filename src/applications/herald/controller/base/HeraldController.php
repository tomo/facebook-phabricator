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

abstract class HeraldController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Herald');
    $page->setBaseURI('/herald/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x98\xBF");
    $page->appendChild($view);

    $doclink = PhabricatorEnv::getDoclink('article/Herald_User_Guide.html');

    $page->setTabs(
      array(
        'rules' => array(
          'href' => '/herald/',
          'name' => 'Rules',
        ),
        'test' => array(
          'href' => '/herald/test/',
          'name' => 'Test Console',
        ),
        'transcripts' => array(
          'href' => '/herald/transcript/',
          'name' => 'Transcripts',
        ),
        'help' => array(
          'href' => $doclink,
          'name' => 'Help',
        ),
      ),
      idx($data, 'tab'));

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());

  }
}
