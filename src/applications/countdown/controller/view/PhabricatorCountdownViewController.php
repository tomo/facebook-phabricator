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

class PhabricatorCountdownViewController
  extends PhabricatorCountdownController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }


  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();
    $timer = id(new PhabricatorTimer())->load($this->id);
    if (!$timer) {
      return new Aphront404Response();
    }

    require_celerity_resource('phabricator-countdown-css');

    $content =
      '<div class="phabricator-timer">
        <h1 class="phabricator-timer-header">'.
          phutil_escape_html($timer->getTitle()).'
        </h1>
        <div class="phabricator-timer-pane">
          <table class="phabricator-timer-table">
            <tr>
              <th>Days</th>
              <th>Hours</th>
              <th>Minutes</th>
              <th>Seconds</th>
            </tr>
            <tr>
              <td id="phabricator-timer-days"></td>
              <td id="phabricator-timer-hours"></td>
              <td id="phabricator-timer-minutes"></td>
              <td id="phabricator-timer-seconds"></td>
          </table>
        </div>
      </div>';

    Javelin::initBehavior('countdown-timer', array(
      'timestamp' => $timer->getDatepoint()
    ));

    $panel = $content;

    return $this->buildStandardPageResponse($panel,
      array(
        'title' => 'T-minus..',
        'tab' => 'view',
      ));
  }

}
