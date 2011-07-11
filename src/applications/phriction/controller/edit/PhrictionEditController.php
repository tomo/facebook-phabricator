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

class PhrictionEditController
  extends PhrictionController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $slug = $request->getStr('slug');
    $slug = PhrictionDocument::normalizeSlug($slug);

    if ($this->id) {
      $document = id(new PhrictionDocument())->load($this->id);
      if (!$document) {
        return new Aphront404Response();
      }
      $content = id(new PhrictionContent())->load($document->getContentID());
    } else if ($slug) {
      $document = null;
      if ($slug) {
        $document = id(new PhrictionDocument())->loadOneWhere(
          'slug = %s',
          $slug);
      }

      if ($document) {
        $content = id(new PhrictionContent())->load($document->getContentID());
      } else {
        $document = new PhrictionDocument();
        $document->setSlug($slug);

        $content  = new PhrictionContent();
        $content->setSlug($slug);

        $default_title = null;
        if ($slug) {
          $default_title = end(explode('/', trim($slug, '/')));
          $default_title = str_replace('_', ' ', $default_title);
          $default_title = ucwords($default_title);
        }
        $default_title = nonempty($default_title, 'New Document');
        $content->setTitle($default_title);
      }
    } else {
      return new Aphront404Response();
    }

    $e_title = true;
    $errors = array();

    if ($request->isFormPost()) {
      $title = $request->getStr('title');

      if (!strlen($title)) {
        $e_title = 'Required';
        $errors[] = 'Document title is required.';
      } else {
        $e_title = null;
      }

      if (!count($errors)) {

        // TODO: This should all be transactional.

        if (!$document->getID()) {
          $document->save();
        }

        $new_content = new PhrictionContent();
        $new_content->setSlug($document->getSlug());
        $new_content->setTitle($title);
        $new_content->setContent($request->getStr('content'));

        $new_content->setDocumentID($document->getID());
        $new_content->setVersion($content->getVersion() + 1);

        $new_content->setAuthorPHID($user->getPHID());
        $new_content->save();

        $document->setContentID($new_content->getID());
        $document->save();

        $uri = PhrictionDocument::getSlugURI($document->getSlug());
        return id(new AphrontRedirectResponse())->setURI($uri);
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    }

    if ($document->getID()) {
      $panel_header = 'Edit Phriction Document';
      $submit_button = 'Edit Document';
    } else {
      $panel_header = 'Create New Phriction Document';
      $submit_button = 'Create Document';
    }

    $uri = $document->getSlug();
    $uri = PhrictionDocument::getSlugURI($uri);
    $uri = PhabricatorEnv::getProductionURI($uri);

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction($request->getRequestURI()->getPath())
      ->addHiddenInput('slug', $slug)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Title')
          ->setValue($content->getTitle())
          ->setError($e_title)
          ->setName('title'))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('URI')
          ->setValue($uri))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Content')
          ->setValue($content->getContent())
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
          ->setName('content'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($submit_button));

    $panel = id(new AphrontPanelView())
      ->setWidth(AphrontPanelView::WIDTH_WIDE)
      ->setHeader($panel_header)
      ->appendChild($form);

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => 'Edit Document',
      ));
  }

}
