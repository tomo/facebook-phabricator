<?php

final class PhabricatorConfigEditController
  extends PhabricatorConfigController {

  private $key;

  public function willProcessRequest(array $data) {
    $this->key = $data['key'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();


    $options = PhabricatorApplicationConfigOptions::loadAllOptions();
    if (empty($options[$this->key])) {
      // This may be a dead config entry, which existed in the past but no
      // longer exists. Allow it to be edited so it can be reviewed and
      // deleted.
      $option = id(new PhabricatorConfigOption())
        ->setKey($this->key)
        ->setType('wild')
        ->setDefault(null)
        ->setDescription(
          pht(
            "This configuration option is unknown. It may be misspelled, ".
            "or have existed in a previous version of Phabricator."));
      $group = null;
      $group_uri = $this->getApplicationURI();
    } else {
      $option = $options[$this->key];
      $group = $option->getGroup();
      $group_uri = $this->getApplicationURI('group/'.$group->getKey().'/');
    }

    $issue = $request->getStr('issue');
    if ($issue) {
      // If the user came here from an open setup issue, send them back.
      $done_uri = $this->getApplicationURI('issue/'.$issue.'/');
    } else {
      $done_uri = $group_uri;
    }

    // Check if the config key is already stored in the database.
    // Grab the value if it is.
    $config_entry = id(new PhabricatorConfigEntry())
      ->loadOneWhere(
        'configKey = %s AND namespace = %s',
        $this->key,
        'default');
    if (!$config_entry) {
      $config_entry = id(new PhabricatorConfigEntry())
        ->setConfigKey($this->key)
        ->setNamespace('default')
        ->setIsDeleted(true);
    }

    $e_value = null;
    $errors = array();
    if ($request->isFormPost()) {

      list($e_value, $value_errors, $display_value) = $this->readRequest(
        $option,
        $config_entry,
        $request);

      $errors = array_merge($errors, $value_errors);

      if (!$errors) {
        $config_entry->save();
        return id(new AphrontRedirectResponse())->setURI($done_uri);
      }
    } else {
      $display_value = $this->getDisplayValue($option, $config_entry);
    }

    $form = new AphrontFormView();
    $form->setFlexible(true);

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('You broke everything!'))
        ->setErrors($errors);
    }

    $control = $this->renderControl(
      $option,
      $display_value,
      $e_value);

    $engine = new PhabricatorMarkupEngine();
    $engine->addObject($option, 'description');
    $engine->process();
    $description = phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $engine->getOutput($option, 'description'));

    $form
      ->setUser($user)
      ->addHiddenInput('issue', $request->getStr('issue'))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Description')
          ->setValue($description))
      ->appendChild($control)
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($done_uri)
          ->setValue(pht('Save Config Entry')));


    // TODO: This isn't quite correct -- we should read from the entire
    // configuration stack, ignoring database configuration. For now, though,
    // it's a reasonable approximation.
    $default = $this->prettyPrintJSON($option->getDefault());
    $form
      ->appendChild(
        phutil_render_tag(
          'p',
          array(
            'class' => 'aphront-form-input',
          ),
          'If left blank, the setting will return to its default value. '.
          'Its default value is:'))
      ->appendChild(
          phutil_render_tag(
            'pre',
            array(
              'class' => 'aphront-form-input',
            ),
            phutil_escape_html($default)));

      $title = pht('Edit %s', $this->key);
      $short = pht('Edit');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Config'))
        ->setHref($this->getApplicationURI()));

    if ($group) {
      $crumbs->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($group->getName())
          ->setHref($group_uri));
    }

    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($this->key)
        ->setHref('/config/edit/'.$this->key));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        id(new PhabricatorHeaderView())->setHeader($title),
        $error_view,
        $form,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function readRequest(
    PhabricatorConfigOption $option,
    PhabricatorConfigEntry $entry,
    AphrontRequest $request) {

    $e_value = null;
    $errors = array();

    $value = $request->getStr('value');
    if (!strlen($value)) {
      $value = null;
      $entry->setValue($value);
      $entry->setIsDeleted(true);
      return array($e_value, $errors, $value);
    } else {
      $entry->setIsDeleted(false);
    }

    $type = $option->getType();
    switch ($type) {
      case 'int':
        if (preg_match('/^-?[0-9]+$/', trim($value))) {
          $entry->setValue((int)$value);
        } else {
          $e_value = pht('Invalid');
          $errors[] = pht('Value must be an integer.');
        }
        break;
      case 'string':
        break;
      case 'bool':
        switch ($value) {
          case 'true':
            $entry->setValue(true);
            break;
          case 'false':
            $entry->setValue(false);
            break;
          default:
            $e_value = pht('Invalid');
            $errors[] = pht('Value must be boolean, "true" or "false".');
            break;
        }
        break;
      default:
        $json = json_decode($value, true);
        if ($json === null && strtolower($value) != 'null') {
          $e_value = pht('Invalid');
          $errors[] = pht(
            'The given value must be valid JSON. This means, among '.
            'other things, that you must wrap strings in double-quotes.');
          $entry->setValue($json);
        }
        break;
    }

    return array($e_value, $errors, $value);
  }

  private function getDisplayValue(
    PhabricatorConfigOption $option,
    PhabricatorConfigEntry $entry) {

    if ($entry->getIsDeleted()) {
      return null;
    }

    $type = $option->getType();
    $value = $entry->getValue();
    switch ($type) {
      case 'int':
      case 'string':
        return $value;
      case 'bool':
        return $value ? 'true' : 'false';
      default:
        return $this->prettyPrintJSON($value);
    }
  }

  private function renderControl(
    PhabricatorConfigOption $option,
    $display_value,
    $e_value) {

    $type = $option->getType();
    switch ($type) {
      case 'int':
      case 'string':
        $control = id(new AphrontFormTextControl());
        break;
      case 'bool':
        $control = id(new AphrontFormSelectControl())
          ->setOptions(
            array(
              ''      => '(Use Default)',
              'true'  => idx($option->getOptions(), 0),
              'false' => idx($option->getOptions(), 1),
            ));
        break;
      default:
        $control = id(new AphrontFormTextAreaControl())
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
          ->setCustomClass('PhabricatorMonospaced')
          ->setCaption(pht('Enter value in JSON.'));
        break;
    }

    $control
      ->setLabel('Value')
      ->setError($e_value)
      ->setValue($display_value)
      ->setName('value');

    return $control;
  }

}
