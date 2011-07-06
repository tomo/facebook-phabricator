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
 * @group aphront
 */
class AphrontDefaultApplicationConfiguration
  extends AphrontApplicationConfiguration {

  public function __construct() {

  }

  public function getApplicationName() {
    return 'aphront-default';
  }

  public function getURIMap() {
    return $this->getResourceURIMapRules() + array(
      '/' => array(
        '$'                     => 'PhabricatorDirectoryMainController',
      ),
      '/directory/' => array(
        'item/$'
          => 'PhabricatorDirectoryItemListController',
        'item/edit/(?:(?P<id>\d+)/)?$'
          => 'PhabricatorDirectoryItemEditController',
        'item/delete/(?P<id>\d+)/'
          => 'PhabricatorDirectoryItemDeleteController',
        'category/$'
          => 'PhabricatorDirectoryCategoryListController',
        'category/edit/(?:(?P<id>\d+)/)?$'
          => 'PhabricatorDirectoryCategoryEditController',
        'category/delete/(?P<id>\d+)/'
          => 'PhabricatorDirectoryCategoryDeleteController',
      ),
      '/file/' => array(
        '$' => 'PhabricatorFileListController',
        'upload/$' => 'PhabricatorFileUploadController',
        'dropupload/$' => 'PhabricatorFileDropUploadController',
        '(?P<view>info)/(?P<phid>[^/]+)/' => 'PhabricatorFileViewController',
        '(?P<view>view)/(?P<phid>[^/]+)/' => 'PhabricatorFileViewController',
        '(?P<view>download)/(?P<phid>[^/]+)/' => 'PhabricatorFileViewController',
        'macro/' => array(
          '$' => 'PhabricatorFileMacroListController',
          'edit/(?:(?P<id>\d+)/)?$' => 'PhabricatorFileMacroEditController',
          'delete/(?P<id>\d+)/$' => 'PhabricatorFileMacroDeleteController',
        ),
        'proxy/$' => 'PhabricatorFileProxyController',
        'xform/(?P<transform>[^/]+)/(?P<phid>[^/]+)/'
          => 'PhabricatorFileTransformController',
      ),
      '/phid/' => array(
        '$' => 'PhabricatorPHIDLookupController',
        'list/$' => 'PhabricatorPHIDListController',
        'new/$' => 'PhabricatorPHIDAllocateController',
      ),
      '/people/' => array(
        '$' => 'PhabricatorPeopleListController',
        'logs/$' => 'PhabricatorPeopleLogsController',
        'edit/(?:(?P<id>\d+)/(?:(?P<view>\w+)/)?)?$'
          => 'PhabricatorPeopleEditController',
      ),
      '/p/(?P<username>\w+)/(?:(?P<page>\w+)/)?$'
        => 'PhabricatorPeopleProfileController',
      '/profile/' => array(
        'edit/$' => 'PhabricatorPeopleProfileEditController',
      ),
      '/conduit/' => array(
        '$' => 'PhabricatorConduitConsoleController',
        'method/(?P<method>[^/]+)$' => 'PhabricatorConduitConsoleController',
        'log/$' => 'PhabricatorConduitLogController',
        'token/$' => 'PhabricatorConduitTokenController',
      ),
      '/api/(?P<method>[^/]+)$' => 'PhabricatorConduitAPIController',

      '/D(?P<id>\d+)' => 'DifferentialRevisionViewController',
      '/differential/' => array(
        '$' => 'DifferentialRevisionListController',
        'filter/(?P<filter>\w+)/$' => 'DifferentialRevisionListController',
        'diff/' => array(
          '(?P<id>\d+)/$' => 'DifferentialDiffViewController',
          'create/$' => 'DifferentialDiffCreateController',
        ),
        'changeset/$'  => 'DifferentialChangesetViewController',
        'revision/edit/(?:(?P<id>\d+)/)?$'
          => 'DifferentialRevisionEditController',
        'comment/' => array(
          'preview/(?P<id>\d+)/$' => 'DifferentialCommentPreviewController',
          'save/$' => 'DifferentialCommentSaveController',
          'inline/' => array(
            'preview/(?P<id>\d+)/$' =>
              'DifferentialInlineCommentPreviewController',
            'edit/(?P<id>\d+)/$' => 'DifferentialInlineCommentEditController',
          ),
        ),
        'subscribe/(?P<action>add|rem)/(?P<id>\d+)/$'
          => 'DifferentialSubscribeController',
      ),

      '/typeahead/' => array(
        'common/(?P<type>\w+)/$'
          => 'PhabricatorTypeaheadCommonDatasourceController',
      ),

      '/mail/' => array(
        '$' => 'PhabricatorMetaMTAListController',
        'send/$' => 'PhabricatorMetaMTASendController',
        'view/(?P<id>\d+)/$' => 'PhabricatorMetaMTAViewController',
        'lists/$' => 'PhabricatorMetaMTAMailingListsController',
        'lists/edit/(?:(?P<id>\d+)/)?$'
          => 'PhabricatorMetaMTAMailingListEditController',
        'receive/$' => 'PhabricatorMetaMTAReceiveController',
        'received/$' => 'PhabricatorMetaMTAReceivedListController',
        'sendgrid/$' => 'PhabricatorMetaMTASendGridReceiveController',
      ),

      '/login/' => array(
        '$' => 'PhabricatorLoginController',
        'email/$' => 'PhabricatorEmailLoginController',
        'etoken/(?P<token>\w+)/$' => 'PhabricatorEmailTokenController',
      ),

      '/logout/$' => 'PhabricatorLogoutController',

      '/oauth/' => array(
        '(?P<provider>github|facebook)/' => array(
          'login/$'     => 'PhabricatorOAuthLoginController',
          'diagnose/$'  => 'PhabricatorOAuthDiagnosticsController',
          'unlink/$'    => 'PhabricatorOAuthUnlinkController',
        ),
      ),

      '/xhprof/' => array(
        'profile/(?P<phid>[^/]+)/$' => 'PhabricatorXHProfProfileController',
      ),

      '/~/' => 'DarkConsoleController',

      '/settings/' => array(
        '(?:page/(?P<page>[^/]+)/)?$' => 'PhabricatorUserSettingsController',
      ),

      '/maniphest/' => array(
        '$' => 'ManiphestTaskListController',
        'view/(?P<view>\w+)/$' => 'ManiphestTaskListController',
        'task/' => array(
          'create/$' => 'ManiphestTaskEditController',
          'edit/(?P<id>\d+)/$' => 'ManiphestTaskEditController',
          'descriptionchange/(?P<id>\d+)/$' =>
            'ManiphestTaskDescriptionChangeController',
        ),
        'transaction/' => array(
          'save/' => 'ManiphestTransactionSaveController',
          'preview/(?P<id>\d+)/$' => 'ManiphestTransactionPreviewController',
        ),
      ),

      '/T(?P<id>\d+)$' => 'ManiphestTaskDetailController',

      '/github-post-receive/(?P<id>\d+)/(?P<token>[^/]+)/$'
        => 'PhabricatorRepositoryGitHubPostReceiveController',

      '/repository/' => array(
        '$'                     => 'PhabricatorRepositoryListController',
        'create/$'              => 'PhabricatorRepositoryCreateController',
        'edit/(?P<id>\d+)/(?:(?P<view>\w+)?/)?$' =>
          'PhabricatorRepositoryEditController',
        'delete/(?P<id>\d+)/$'  => 'PhabricatorRepositoryDeleteController',
        'project/(?P<id>\d+)/' =>
          'PhabricatorRepositoryArcanistProjectEditController',
      ),

      '/search/' => array(
        '$' => 'PhabricatorSearchController',
        '(?P<id>\d+)/$' => 'PhabricatorSearchController',
        'attach/(?P<phid>[^/]+)/(?P<type>\w+)/(?:(?P<action>\w+)/)?$'
          => 'PhabricatorSearchAttachController',
        'select/(?P<type>\w+)/$'
          => 'PhabricatorSearchSelectController',
        'index/(?P<phid>[^/]+)/$' => 'PhabricatorSearchIndexController',
      ),

      '/project/' => array(
        '$' => 'PhabricatorProjectListController',
        'edit/(?P<id>\d+)/$' => 'PhabricatorProjectProfileEditController',
        'view/(?P<id>\d+)/(?:(?P<page>\w+)/)?$'
          => 'PhabricatorProjectProfileController',
        'affiliation/(?P<id>\d+)/$'
          => 'PhabricatorProjectAffiliationEditController',
        'create/$' => 'PhabricatorProjectCreateController',
      ),

      '/r(?P<callsign>[A-Z]+)(?P<commit>[a-z0-9]+)$'
        => 'DiffusionCommitController',
      '/diffusion/' => array(
        '$' => 'DiffusionHomeController',
        '(?P<callsign>[A-Z]+)/' => array(
          '$' => 'DiffusionRepositoryController',
          'repository/'.
            '(?P<path>[^/]+)/'.
            '$'
              => 'DiffusionRepositoryController',
          'change/'.
            '(?P<path>.*?)'.
            '(?:[;](?P<commit>[a-z0-9]+))?'.
            '$'
              => 'DiffusionChangeController',
          'history/'.
            '(?P<path>.*?)'.
            '(?:[;](?P<commit>[a-z0-9]+))?'.
            '$'
              => 'DiffusionHistoryController',
          'browse/'.
            '(?P<path>.*?)'.
            '(?:[;](?P<commit>[a-z0-9]+))?'.
            '(?:[$](?P<line>\d+))?'.
            '$'
              => 'DiffusionBrowseController',
          'diff/'.
            '(?P<path>.*?)'.
            '(?:[;](?P<commit>[a-z0-9]+))?'.
            '$'
              => 'DiffusionDiffController',
          'lastmodified/'.
            '(?P<path>.*?)'.
            '(?:[;](?P<commit>[a-z0-9]+))?'.
            '$'
              => 'DiffusionLastModifiedController',
        ),
        'services/' => array(
          'path/' => array(
            'complete/$' => 'DiffusionPathCompleteController',
            'validate/$' => 'DiffusionPathValidateController',
          ),
        ),
      ),

      '/daemon/' => array(
        'task/(?P<id>\d+)/$' => 'PhabricatorWorkerTaskDetailController',
        'log/' => array(
          '$' => 'PhabricatorDaemonLogListController',
          'combined/$' => 'PhabricatorDaemonCombinedLogController',
          '(?P<id>\d+)/$' => 'PhabricatorDaemonLogViewController',
        ),
        'timeline/$' => 'PhabricatorDaemonTimelineConsoleController',
        'timeline/(?P<id>\d+)/$' => 'PhabricatorDaemonTimelineEventController',
        '$' => 'PhabricatorDaemonConsoleController',
      ),

      '/herald/' => array(
        '$' => 'HeraldHomeController',
        'view/(?P<view>[^/]+)/$' => 'HeraldHomeController',
        'new/(?:(?P<type>[^/]+)/)?$' => 'HeraldNewController',
        'rule/(?:(?P<id>\d+)/)?$' => 'HeraldRuleController',
        'delete/(?P<id>\d+)/$' => 'HeraldDeleteController',
        'test/$' => 'HeraldTestConsoleController',
        'transcript/$' => 'HeraldTranscriptListController',
        'transcript/(?P<id>\d+)/(?:(?P<filter>\w+)/)?$'
          => 'HeraldTranscriptController',
      ),

      '/preferences/' => array(
        '$' => 'PhabricatorEditPreferencesController'
      ),

      '/uiexample/' => array(
        '$' => 'PhabricatorUIExampleRenderController',
        'view/(?P<class>[^/]+)/$' => 'PhabricatorUIExampleRenderController',
      ),

      '/owners/' => array(
        '$' => 'PhabricatorOwnersListController',
        'view/(?P<view>[^/]+)/$' => 'PhabricatorOwnersListController',
        'edit/(?P<id>\d+)/$' => 'PhabricatorOwnersEditController',
        'new/$' => 'PhabricatorOwnersEditController',
        'package/(?P<id>\d+)/$' => 'PhabricatorOwnersDetailController',
        'delete/(?P<id>\d+)/$' => 'PhabricatorOwnersDeleteController',
      ),

      '/xhpast/' => array(
        '$' => 'PhabricatorXHPASTViewRunController',
        'view/(?P<id>\d+)/$'
          => 'PhabricatorXHPASTViewFrameController',
        'frameset/(?P<id>\d+)/$'
          => 'PhabricatorXHPASTViewFramesetController',
        'input/(?P<id>\d+)/$'
          => 'PhabricatorXHPASTViewInputController',
        'tree/(?P<id>\d+)/$'
          => 'PhabricatorXHPASTViewTreeController',
        'stream/(?P<id>\d+)/$'
          => 'PhabricatorXHPASTViewStreamController',
      ),

      '/status/$' => 'PhabricatorStatusController',

      '/paste/' => array(
        '$' => 'PhabricatorPasteCreateController',
        'list/' => 'PhabricatorPasteListController',
      ),

      '/P(?P<id>\d+)$' => 'PhabricatorPasteViewController',

      '/help/' => array(
        'keyboardshortcut/$' => 'PhabricatorHelpKeyboardShortcutController',
      ),

      '/countdown/' => array(
        '$'
          => 'PhabricatorCountdownListController',
        '(?P<id>\d+)/$'
          => 'PhabricatorCountdownViewController',
        'edit/(?:(?P<id>\d+)/)?$'
          => 'PhabricatorCountdownEditController',
        'delete/(?P<id>\d+)/$'
          => 'PhabricatorCountdownDeleteController'
      ),

      '/feed/' => array(
        '$' => 'PhabricatorFeedStreamController',
      ),
    );
  }

  protected function getResourceURIMapRules() {
    return array(
      '/res/' => array(
        '(?P<package>pkg/)?(?P<hash>[a-f0-9]{8})/(?P<path>.+\.(?:css|js))$'
          => 'CelerityResourceController',
      ),
    );
  }

  public function buildRequest() {
    $request = new AphrontRequest($this->getHost(), $this->getPath());
    $request->setRequestData($_GET + $_POST);
    $request->setApplicationConfiguration($this);
    return $request;
  }

  public function handleException(Exception $ex) {

    // Always log the unhandled exception.
    phlog($ex);

    $class    = phutil_escape_html(get_class($ex));
    $message  = phutil_escape_html($ex->getMessage());

    $content =
      '<div class="aphront-unhandled-exception">'.
        '<h1>Unhandled Exception "'.$class.'": '.$message.'</h1>'.
        '<code>'.phutil_escape_html((string)$ex).'</code>'.
      '</div>';

    $user = $this->getRequest()->getUser();
    if (!$user) {
      // If we hit an exception very early, we won't have a user.
      $user = new PhabricatorUser();
    }

    $dialog = new AphrontDialogView();
    $dialog
      ->setTitle('Exception!')
      ->setClass('aphront-exception-dialog')
      ->setUser($user)
      ->appendChild($content)
      ->addCancelButton('/');

    $response = new AphrontDialogResponse();
    $response->setDialog($dialog);

    return $response;
  }

  public function willSendResponse(AphrontResponse $response) {
    $request = $this->getRequest();
    $response->setRequest($request);
    if ($response instanceof AphrontDialogResponse) {
      if (!$request->isAjax()) {
        $view = new PhabricatorStandardPageView();
        $view->setRequest($request);
        $view->appendChild(
          '<div style="padding: 2em 0;">'.
            $response->buildResponseString().
          '</div>');
        $response = new AphrontWebpageResponse();
        $response->setContent($view->render());
        return $response;
      } else {
        return id(new AphrontAjaxResponse())
          ->setContent(array(
            'dialog' => $response->buildResponseString(),
          ));
      }
    } else if ($response instanceof AphrontRedirectResponse) {
      if ($request->isAjax()) {
        return id(new AphrontAjaxResponse())
          ->setContent(
            array(
              'redirect' => $response->getURI(),
            ));
      }
    } else if ($response instanceof Aphront404Response) {

      $failure = new AphrontRequestFailureView();
      $failure->setHeader('404 Not Found');
      $failure->appendChild(
        '<p>The page you requested was not found.</p>');

      $view = new PhabricatorStandardPageView();
      $view->setTitle('404 Not Found');
      $view->setRequest($this->getRequest());
      $view->appendChild($failure);

      $response = new AphrontWebpageResponse();
      $response->setContent($view->render());
      $response->setHTTPResponseCode(404);
      return $response;
    }

    return $response;
  }

  public function build404Controller() {
    return array(new Phabricator404Controller($this->getRequest()), array());
  }

  public function buildRedirectController($uri) {
    return array(
      new PhabricatorRedirectController($this->getRequest()),
      array(
        'uri' => $uri,
      ));
  }

}
