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

final class DiffusionCommitTagsController extends DiffusionController {

  public function willProcessRequest(array $data) {
    $this->diffusionRequest = DiffusionRequest::newFromDictionary($data);
  }

  public function processRequest() {
    $request = $this->getDiffusionRequest();
    $tag_limit = 10;

    $tag_query = DiffusionCommitTagsQuery::newFromDiffusionRequest($request);
    $tag_query->setLimit($tag_limit + 1);
    $tags = $tag_query->loadTags();

    $has_more_tags = (count($tags) > $tag_limit);
    $tags = array_slice($tags, 0, $tag_limit);

    $tag_links = array();
    foreach ($tags as $tag) {
      $tag_links[] = phutil_render_tag(
        'a',
        array(
          'href' => $request->generateURI(
            array(
              'action'  => 'browse',
              'commit'  => $tag->getName(),
            )),
        ),
        phutil_escape_html($tag->getName()));
    }

    if ($has_more_tags) {
      $tag_links[] = phutil_render_tag(
        'a',
        array(
          'href' => $request->generateURI(
            array(
              'action'  => 'tags',
            )),
        ),
        "More tags\xE2\x80\xA6");
    }

    return id(new AphrontAjaxResponse())
      ->setContent($tag_links ? implode(', ', $tag_links) : 'None');
  }
}
