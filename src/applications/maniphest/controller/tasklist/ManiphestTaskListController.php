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
 * @group maniphest
 */
class ManiphestTaskListController extends ManiphestController {

  const DEFAULT_PAGE_SIZE = 1000;

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      // Redirect to GET so URIs can be copy/pasted.

      $user_phids = $request->getArr('set_users');
      $proj_phids = $request->getArr('set_projects');
      $task_ids   = $request->getStr('set_tasks');
      $user_phids = implode(',', $user_phids);
      $proj_phids = implode(',', $proj_phids);
      $user_phids = nonempty($user_phids, null);
      $proj_phids = nonempty($proj_phids, null);
      $task_ids   = nonempty($task_ids, null);

      $uri = $request->getRequestURI()
        ->alter('users', $user_phids)
        ->alter('projects', $proj_phids)
        ->alter('tasks', $task_ids);

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/maniphest/view/'));
    $nav->addLabel('User Tasks');
    $nav->addFilter('action',       'Assigned');
    $nav->addFilter('created',      'Created');
    $nav->addFilter('subscribed',   'Subscribed');
    $nav->addFilter('triage',       'Need Triage');
    $nav->addSpacer();
    $nav->addLabel('All Tasks');
    $nav->addFilter('alltriage',    'Need Triage');
    $nav->addFilter('all',          'All Tasks');
    $nav->addSpacer();
    $nav->addFilter('custom',       'Custom');

    $this->view = $nav->selectFilter($this->view, 'action');

    $has_filter = array(
      'action' => true,
      'created' => true,
      'subscribed' => true,
      'triage' => true,
    );

    list($status_map, $status_control) = $this->renderStatusLinks();
    list($grouping, $group_control) = $this->renderGroupLinks();
    list($order, $order_control) = $this->renderOrderLinks();

    $user_phids = $request->getStr('users');
    if (strlen($user_phids)) {
      $user_phids = explode(',', $user_phids);
    } else {
      $user_phids = array($user->getPHID());
    }

    $project_phids = $request->getStr('projects');
    if (strlen($project_phids)) {
      $project_phids = explode(',', $project_phids);
    } else {
      $project_phids = array();
    }

    $task_ids = $request->getStrList('tasks');

    $page = $request->getInt('page');
    $page_size = self::DEFAULT_PAGE_SIZE;

    list($tasks, $handles, $total_count) = $this->loadTasks(
      $user_phids,
      $project_phids,
      $task_ids,
      array(
        'status'  => $status_map,
        'group'   => $grouping,
        'order'   => $order,
        'offset'  => $page,
        'limit'   => $page_size,
      ));

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction($request->getRequestURI());

    if (isset($has_filter[$this->view])) {
      $tokens = array();
      foreach ($user_phids as $phid) {
        $tokens[$phid] = $handles[$phid]->getFullName();
      }
      $form->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/searchowner/')
          ->setName('set_users')
          ->setLabel('Users')
          ->setValue($tokens));
    }

    if ($this->view == 'custom') {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setName('set_tasks')
          ->setLabel('Task IDs')
          ->setValue(join(',', $task_ids))
      );
    }

    $tokens = array();
    foreach ($project_phids as $phid) {
      $tokens[$phid] = $handles[$phid]->getFullName();
    }
    $form->appendChild(
      id(new AphrontFormTokenizerControl())
        ->setDatasource('/typeahead/common/projects/')
        ->setName('set_projects')
        ->setLabel('Projects')
        ->setValue($tokens));

    $form
      ->appendChild($status_control)
      ->appendChild($group_control)
      ->appendChild($order_control);

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue('Filter Tasks'));

    $create_uri = new PhutilURI('/maniphest/task/create/');
    if ($project_phids) {
      // If we have project filters selected, use them as defaults for task
      // creation.
      $create_uri->setQueryParam('projects', implode(';', $project_phids));
    }

    $filter = new AphrontListFilterView();
    $filter->addButton(
      phutil_render_tag(
        'a',
        array(
          'href'  => (string)$create_uri,
          'class' => 'green button',
        ),
        'Create New Task'));
    $filter->appendChild($form);

    $nav->appendChild($filter);

    $have_tasks = false;
    foreach ($tasks as $group => $list) {
      if (count($list)) {
        $have_tasks = true;
        break;
      }
    }

    require_celerity_resource('maniphest-task-summary-css');

    if (!$have_tasks) {
      $nav->appendChild(
        '<h1 class="maniphest-task-group-header">'.
          'No matching tasks.'.
        '</h1>');
    } else {
      $pager = new AphrontPagerView();
      $pager->setURI($request->getRequestURI(), 'page');
      $pager->setPageSize($page_size);
      $pager->setOffset($page);
      $pager->setCount($total_count);

      $cur = ($pager->getOffset() + 1);
      $max = min($pager->getOffset() + $page_size, $total_count);
      $tot = $total_count;

      $cur = number_format($cur);
      $max = number_format($max);
      $tot = number_format($tot);

      $nav->appendChild(
        '<div class="maniphest-total-result-count">'.
          "Displaying tasks {$cur} - {$max} of {$tot}.".
        '</div>');

      $selector = new AphrontNullView();

      foreach ($tasks as $group => $list) {
        $task_list = new ManiphestTaskListView();
        $task_list->setShowBatchControls(true);
        $task_list->setUser($user);
        $task_list->setTasks($list);
        $task_list->setHandles($handles);

        $count = number_format(count($list));
        $selector->appendChild(
          '<h1 class="maniphest-task-group-header">'.
            phutil_escape_html($group).' ('.$count.')'.
          '</h1>');
        $selector->appendChild($task_list);
      }


      $selector->appendChild($this->renderBatchEditor());

      $selector = phabricator_render_form(
        $user,
        array(
          'method' => 'POST',
          'action' => '/maniphest/batch/',
        ),
        $selector->render());

      $nav->appendChild($selector);
      $nav->appendChild($pager);
    }

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Task List',
      ));
  }

  private function loadTasks(
    array $user_phids,
    array $project_phids,
    array $task_ids,
    array $dict) {

    $query = new ManiphestTaskQuery();
    $query->withProjects($project_phids);
    $query->withTaskIDs($task_ids);

    $status = $dict['status'];
    if (!empty($status['open']) && !empty($status['closed'])) {
      $query->withStatus(ManiphestTaskQuery::STATUS_ANY);
    } else if (!empty($status['open'])) {
      $query->withStatus(ManiphestTaskQuery::STATUS_OPEN);
    } else {
      $query->withStatus(ManiphestTaskQuery::STATUS_CLOSED);
    }

    switch ($this->view) {
      case 'action':
        $query->withOwners($user_phids);
        break;
      case 'created':
        $query->withAuthors($user_phids);
        break;
      case 'subscribed':
        $query->withSubscribers($user_phids);
        break;
      case 'triage':
        $query->withOwners($user_phids);
        $query->withPriority(ManiphestTaskPriority::PRIORITY_TRIAGE);
        break;
      case 'alltriage':
        $query->withPriority(ManiphestTaskPriority::PRIORITY_TRIAGE);
        break;
      case 'all':
        break;
    }

    $order_map = array(
      'priority'  => ManiphestTaskQuery::ORDER_PRIORITY,
      'created'   => ManiphestTaskQuery::ORDER_CREATED,
    );
    $query->setOrderBy(
      idx(
        $order_map,
        $dict['order'],
        ManiphestTaskQuery::ORDER_MODIFIED));

    $group_map = array(
      'priority'  => ManiphestTaskQuery::GROUP_PRIORITY,
      'owner'     => ManiphestTaskQuery::GROUP_OWNER,
      'status'    => ManiphestTaskQuery::GROUP_STATUS,
    );
    $query->setGroupBy(
      idx(
        $group_map,
        $dict['group'],
        ManiphestTaskQuery::GROUP_NONE));

    $query->setCalculateRows(true);
    $query->setLimit($dict['limit']);
    $query->setOffset($dict['offset']);

    $data = $query->execute();
    $total_row_count = $query->getRowCount();

    $handle_phids = mpull($data, 'getOwnerPHID');
    $handle_phids = array_merge($handle_phids, $project_phids, $user_phids);
    $handles = id(new PhabricatorObjectHandleData($handle_phids))
      ->loadHandles();

    switch ($dict['group']) {
      case 'priority':
        $data = mgroup($data, 'getPriority');
        krsort($data);

        // If we have invalid priorities, they'll all map to "???". Merge
        // arrays to prevent them from overwriting each other.

        $out = array();
        foreach ($data as $pri => $tasks) {
          $out[ManiphestTaskPriority::getTaskPriorityName($pri)][] = $tasks;
        }
        foreach ($out as $pri => $tasks) {
          $out[$pri] = array_mergev($tasks);
        }
        $data = $out;

        break;
      case 'status':
        $data = mgroup($data, 'getStatus');
        ksort($data);

        $out = array();
        foreach ($data as $status => $tasks) {
          $out[ManiphestTaskStatus::getTaskStatusFullName($status)] = $tasks;
        }

        $data = $out;
        break;
      case 'owner':
        $data = mgroup($data, 'getOwnerPHID');

        $out = array();
        foreach ($data as $phid => $tasks) {
          if ($phid) {
            $out[$handles[$phid]->getFullName()] = $tasks;
          } else {
            $out['Unassigned'] = $tasks;
          }
        }
        if (isset($out['Unassigned'])) {
          // If any tasks are unassigned, move them to the front of the list.
          $data = array('Unassigned' => $out['Unassigned']) + $out;
        } else {
          $data = $out;
        }

        ksort($data);
        break;
      default:
        $data = array(
          'Tasks' => $data,
        );
        break;
    }

    return array($data, $handles, $total_row_count);
  }

  public function renderStatusLinks() {
    $request = $this->getRequest();

    $statuses = array(
      'o'   => array('open' => true),
      'c'   => array('closed' => true),
      'oc'  => array('open' => true, 'closed' => true),
    );

    $status = $request->getStr('s');
    if (empty($statuses[$status])) {
      $status = 'o';
    }

    $status_control = id(new AphrontFormToggleButtonsControl())
      ->setLabel('Status')
      ->setValue($status)
      ->setBaseURI($request->getRequestURI(), 's')
      ->setButtons(
        array(
          'o'   => 'Open',
          'c'   => 'Closed',
          'oc'  => 'All',
        ));

    return array($statuses[$status], $status_control);
  }

  public function renderOrderLinks() {
    $request = $this->getRequest();

    $order = $request->getStr('o');
    $orders = array(
      'u' => 'updated',
      'c' => 'created',
      'p' => 'priority',
    );
    if (empty($orders[$order])) {
      $order = 'p';
    }
    $order_by = $orders[$order];

    $order_control = id(new AphrontFormToggleButtonsControl())
      ->setLabel('Order')
      ->setValue($order)
      ->setBaseURI($request->getRequestURI(), 'o')
      ->setButtons(
        array(
          'p' => 'Priority',
          'u' => 'Updated',
          'c' => 'Created',
        ));

    return array($order_by, $order_control);
  }

  public function renderGroupLinks() {
    $request = $this->getRequest();

    $group = $request->getStr('g');
    $groups = array(
      'n' => 'none',
      'p' => 'priority',
      's' => 'status',
      'o' => 'owner',
    );
    if (empty($groups[$group])) {
      $group = 'p';
    }
    $group_by = $groups[$group];


    $group_control = id(new AphrontFormToggleButtonsControl())
      ->setLabel('Group')
      ->setValue($group)
      ->setBaseURI($request->getRequestURI(), 'g')
      ->setButtons(
        array(
          'p' => 'Priority',
          'o' => 'Owner',
          's' => 'Status',
          'n' => 'None',
        ));

    return array($group_by, $group_control);
  }

  private function renderBatchEditor() {
    Javelin::initBehavior(
      'maniphest-batch-selector',
      array(
        'selectAll'   => 'batch-select-all',
        'selectNone'  => 'batch-select-none',
        'submit'      => 'batch-select-submit',
        'status'      => 'batch-select-status-cell',
      ));

    $select_all = javelin_render_tag(
      'a',
      array(
        'href'        => '#',
        'mustcapture' => true,
        'class'       => 'grey button',
        'id'          => 'batch-select-all',
      ),
      'Select All');

    $select_none = javelin_render_tag(
      'a',
      array(
        'href'        => '#',
        'mustcapture' => true,
        'class'       => 'grey button',
        'id'          => 'batch-select-none',
      ),
      'Clear Selection');

    $submit = phutil_render_tag(
      'button',
      array(
        'id'          => 'batch-select-submit',
        'disabled'    => 'disabled',
        'class'       => 'disabled',
      ),
      'Batch Edit Selected Tasks &raquo;');

    return
      '<div class="maniphest-batch-editor">'.
        '<div class="batch-editor-header">Batch Task Editor</div>'.
        '<table class="maniphest-batch-editor-layout">'.
          '<tr>'.
            '<td>'.
              $select_all.
              $select_none.
            '</td>'.
            '<td id="batch-select-status-cell">'.
              '0 Selected Tasks'.
            '</td>'.
            '<td class="batch-select-submit-cell">'.$submit.'</td>'.
          '</tr>'.
        '</table>'.
      '</table>';
  }

}
