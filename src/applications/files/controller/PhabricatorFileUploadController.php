<?php

final class PhabricatorFileUploadController extends PhabricatorFileController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $e_file = true;
    $errors = array();
    if ($request->isFormPost()) {
      if (!$request->getFileExists('file')) {
        $e_file = pht('Required');
        $errors[] = pht('You must select a file to upload.');
      } else {
        $file = PhabricatorFile::newFromPHPUpload(
          idx($_FILES, 'file'),
          array(
            'name'        => $request->getStr('name'),
            'authorPHID'  => $user->getPHID(),
          ));
      }

      if (!$errors) {
        return id(new AphrontRedirectResponse())->setURI($file->getViewURI());
      }
    }

    $support_id = celerity_generate_unique_node_id();
    $instructions = id(new AphrontFormMarkupControl())
      ->setControlID($support_id)
      ->setControlStyle('display: none')
      ->setValue(
        '<br /><br />'.
        pht(
          '<strong>Drag and Drop:</strong> You can also upload files by '.
          'dragging and dropping them from your desktop onto this page or '.
          'the Phabricator home page.').
        '<br /><br />');

    $form = id(new AphrontFormView())
      ->setFlexible(true)
      ->setUser($user)
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel(pht('File'))
          ->setName('file')
          ->setError($e_file)
          ->setCaption($this->renderUploadLimit()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($request->getStr('name'))
          ->setCaption('Optional file display name.'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Upload'))
          ->addCancelButton('/file/'))
      ->appendChild($instructions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Upload'))
        ->setHref($request->getRequestURI()));

    $header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Upload File'));

    if ($errors) {
      $errors = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    }

    $global_upload = id(new PhabricatorGlobalUploadTargetView())
      ->setShowIfSupportedID($support_id);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $errors,
        $form,
        $global_upload,
      ),
      array(
        'title' => 'Upload File',
        'device' => true,
      ));
  }

  private function renderUploadLimit() {
    $limit = PhabricatorEnv::getEnvConfig('storage.upload-size-limit');
    $limit = phabricator_parse_bytes($limit);
    if ($limit) {
      $formatted = phabricator_format_bytes($limit);
      return 'Maximum file size: '.phutil_escape_html($formatted);
    }

    $doc_href = PhabricatorEnv::getDocLink(
      'article/Configuring_File_Upload_Limits.html');
    $doc_link = phutil_render_tag(
      'a',
      array(
        'href'    => $doc_href,
        'target'  => '_blank',
      ),
      'Configuring File Upload Limits');

    return 'Upload limit is not configured, see '.$doc_link.'.';
  }

}
