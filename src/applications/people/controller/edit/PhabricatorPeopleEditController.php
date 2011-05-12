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

class PhabricatorPeopleEditController extends PhabricatorPeopleController {

  public function shouldRequireAdmin() {
    return true;
  }

  private $id;
  private $view;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
    $this->view = idx($data, 'view');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $admin = $request->getUser();

    if ($this->id) {
      $user = id(new PhabricatorUser())->load($this->id);
      if (!$user) {
        return new Aphront404Response();
      }
    } else {
      $user = new PhabricatorUser();
    }

    $views = array(
      'basic'     => 'Basic Information',
      'password'  => 'Reset Password',
      'role'      => 'Edit Role',
    );

    if (!$user->getID()) {
      $view = 'basic';
    } else if (isset($views[$this->view])) {
      $view = $this->view;
    } else {
      $view = 'basic';
    }

    $content = array();

    if ($request->getStr('saved')) {
      $notice = new AphrontErrorView();
      $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
      $notice->setTitle('Changed Saved');
      $notice->appendChild('<p>Your changes were saved.</p>');
      $content[] = $notice;
    }

    switch ($view) {
      case 'basic':
        $response = $this->processBasicRequest($user);
        break;
      case 'password':
        $response = $this->processPasswordRequest($user);
        break;
      case 'role':
        $response = $this->processRoleRequest($user);
        break;
    }

    if ($response instanceof AphrontResponse) {
      return $response;
    }

    $content[] = $response;

    if ($user->getID()) {
      $side_nav = new AphrontSideNavView();
      $side_nav->appendChild($content);
      foreach ($views as $key => $name) {
        $side_nav->addNavItem(
          phutil_render_tag(
            'a',
            array(
              'href' => '/people/edit/'.$user->getID().'/'.$key.'/',
              'class' => ($key == $view)
                ? 'aphront-side-nav-selected'
                : null,
            ),
            phutil_escape_html($name)));
      }
      $content = $side_nav;
    }

    return $this->buildStandardPageResponse(
      $content,
      array(
        'title' => 'Edit User',
      ));
  }

  private function processBasicRequest(PhabricatorUser $user) {
    $request = $this->getRequest();
    $admin = $request->getUser();

    $e_username = true;
    $e_realname = true;
    $e_email    = true;
    $errors = array();

    $request = $this->getRequest();
    if ($request->isFormPost()) {
      if (!$user->getID()) {
        $user->setUsername($request->getStr('username'));
      }
      $user->setRealName($request->getStr('realname'));
      $user->setEmail($request->getStr('email'));

      if (!strlen($user->getUsername())) {
        $errors[] = "Username is required.";
        $e_username = 'Required';
      } else if (!preg_match('/^[a-z0-9]+$/', $user->getUsername())) {
        $errors[] = "Username must consist of only numbers and letters.";
        $e_username = 'Invalid';
      } else {
        $e_username = null;
      }

      if (!strlen($user->getRealName())) {
        $errors[] = 'Real name is required.';
        $e_realname = 'Required';
      } else {
        $e_realname = null;
      }

      if (!strlen($user->getEmail())) {
        $errors[] = 'Email is required.';
        $e_email = 'Required';
      } else {
        $e_email = null;
      }

      if (!$errors) {
        try {
          $user->save();
          $response = id(new AphrontRedirectResponse())
            ->setURI('/people/edit/'.$user->getID().'/?saved=true');
          return $response;
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $errors[] = 'Username and email must be unique.';

          $same_username = id(new PhabricatorUser())
            ->loadOneWhere('username = %s', $user->getUsername());
          $same_email = id(new PhabricatorUser())
            ->loadOneWhere('email = %s', $user->getEmail());

          if ($same_username) {
            $e_username = 'Duplicate';
          }

          if ($same_email) {
            $e_email = 'Duplicate';
          }
        }
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    }

    $form = new AphrontFormView();
    $form->setUser($admin);
    if ($user->getID()) {
      $form->setAction('/people/edit/'.$user->getID().'/');
    } else {
      $form->setAction('/people/edit/');
    }

    if ($user->getID()) {
      $is_immutable = true;
    } else {
      $is_immutable = false;
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Username')
          ->setName('username')
          ->setValue($user->getUsername())
          ->setError($e_username)
          ->setDisabled($is_immutable)
          ->setCaption('Usernames are permanent and can not be changed later!'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Real Name')
          ->setName('realname')
          ->setValue($user->getRealName())
          ->setError($e_realname))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Email')
          ->setName('email')
          ->setValue($user->getEmail())
          ->setError($e_email))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save')
          ->addCancelButton('/people/'));

    $panel = new AphrontPanelView();
    if ($user->getID()) {
      $panel->setHeader('Edit User');
    } else {
      $panel->setHeader('Create New User');
    }

    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    return array($error_view, $panel);
  }

  private function processPasswordRequest(PhabricatorUser $user) {
    $request = $this->getRequest();
    $admin = $request->getUser();

    $e_password = true;
    $errors = array();

    if ($request->isFormPost()) {
      if (strlen($request->getStr('password'))) {
        $user->setPassword($request->getStr('password'));
        $e_password = null;
      } else {
        $errors[] = 'Password is required.';
        $e_password = 'Required';
      }

      if (!$errors) {
        $user->save();
        return id(new AphrontRedirectResponse())
          ->setURI($request->getRequestURI()->alter('saved', 'true'));
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    }


    $form = id(new AphrontFormView())
      ->setUser($admin)
      ->setAction($request->getRequestURI()->alter('saved', null))
      ->appendChild(
        '<p class="aphront-form-instructions">Submitting this form will '.
        'change this user\'s password. They will no longer be able to login '.
        'with their old password.</p>')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('New Password')
          ->setName('password')
          ->setError($e_password))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Reset Password'));


    $panel = new AphrontPanelView();
    $panel->setHeader('Reset Password');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    return array($error_view, $panel);
  }

  private function processRoleRequest(PhabricatorUser $user) {
    $request = $this->getRequest();
    $admin = $request->getUser();

    $is_self = ($user->getID() == $admin->getID());

    $errors = array();

    if ($request->isFormPost()) {
      if ($is_self) {
        $errors[] = "You can not edit your own role.";
      } else {
        $user->setIsAdmin($request->getInt('is_admin'));
        $user->setIsDisabled($request->getInt('is_disabled'));
        $user->setIsSystemAgent($request->getInt('is_agent'));
      }

      if (!$errors) {
        $user->save();
        return id(new AphrontRedirectResponse())
          ->setURI($request->getRequestURI()->alter('saved', 'true'));
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    }


    $form = id(new AphrontFormView())
      ->setUser($admin)
      ->setAction($request->getRequestURI()->alter('saved', null));

    if ($is_self) {
      $form->appendChild(
        '<p class="aphront-form-instructions">NOTE: You can not edit your own '.
        'role.</p>');
    }

    $form
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'is_admin',
            1,
            'Admin: wields absolute power.',
            $user->getIsAdmin())
          ->setDisabled($is_self))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'is_disabled',
            1,
            'Disabled: can not login.',
            $user->getIsDisabled())
          ->setDisabled($is_self))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'is_agent',
            1,
            'Agent: system agent (robot).',
            $user->getIsSystemAgent())
          ->setDisabled($is_self));

    if (!$is_self) {
      $form
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue('Edit Role'));
    }

    $panel = new AphrontPanelView();
    $panel->setHeader('Edit Role');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    return array($error_view, $panel);
  }

}
