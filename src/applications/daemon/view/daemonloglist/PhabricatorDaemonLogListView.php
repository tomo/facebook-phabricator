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

final class PhabricatorDaemonLogListView extends AphrontView {

  private $daemonLogs;

  public function setDaemonLogs(array $daemon_logs) {
    $this->daemonLogs = $daemon_logs;
  }

  public function render() {
    $rows = array();

    foreach ($this->daemonLogs as $log) {
      $epoch = $log->getDateCreated();

      if ($log->getHost() == php_uname('n')) {

        // This will probably fail since apache can't signal the process, but
        // we can check the error code to figure out if the process exists.
        $is_running = posix_kill($log->getPID(), 0);
        if (posix_get_last_error() == 1) {
          // "Operation Not Permitted", indicates that the PID exists. If it
          // doesn't, we'll get an error 3 ("No such process") instead.
          $is_running = true;
        }

        if ($is_running) {
          $running = phutil_render_tag(
            'span',
            array(
              'style' => 'color: #00cc00',
              'title' => 'Running',
            ),
            '&bull;');
        } else {
          $running = phutil_render_tag(
            'span',
            array(
              'style' => 'color: #cc0000',
              'title' => 'Not running',
            ),
            '&bull;');
        }
      } else {
        $running = phutil_render_tag(
          'span',
          array(
            'style' => 'color: #888888',
            'title' => 'Not on this host',
          ),
          '?');
      }

      $rows[] = array(
        $running,
        phutil_escape_html($log->getDaemon()),
        phutil_escape_html($log->getHost()),
        $log->getPID(),
        date('M j, Y', $epoch),
        date('g:i A', $epoch),
        phutil_render_tag(
          'a',
          array(
            'href' => '/daemon/log/'.$log->getID().'/',
            'class' => 'button small grey',
          ),
          'View Log'),
      );
    }

    $daemon_table = new AphrontTableView($rows);
    $daemon_table->setHeaders(
      array(
        '',
        'Daemon',
        'Host',
        'PID',
        'Date',
        'Time',
        'View',
      ));
    $daemon_table->setColumnClasses(
      array(
        '',
        'wide wrap',
        '',
        '',
        '',
        'right',
        'action',
      ));

    return $daemon_table->render();
  }

}
