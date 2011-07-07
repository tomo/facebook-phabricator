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

class HeraldRuleController extends HeraldController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = (int)idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $content_type_map = HeraldContentTypeConfig::getContentTypeMap();

    if ($this->id) {
      $rule = id(new HeraldRule())->load($this->id);
      if (!$rule) {
        return new Aphront404Response();
      }
      if ($rule->getAuthorPHID() != $user->getPHID()) {
        throw new Exception("You don't own this rule and can't edit it.");
      }
    } else {
      $rule = new HeraldRule();
      $rule->setAuthorPHID($user->getPHID());
      $rule->setMustMatchAll(true);

      $type = $request->getStr('type');
      if (!isset($content_type_map[$type])) {
        $type = HeraldContentTypeConfig::CONTENT_TYPE_DIFFERENTIAL;
      }
      $rule->setContentType($type);
    }

    $local_version = id(new HeraldRule())->getConfigVersion();
    if ($rule->getConfigVersion() > $local_version) {
      throw new Exception(
        "This rule was created with a newer version of Herald. You can not ".
        "view or edit it in this older version. Try dev or wait for a push.");
    }

    // Upgrade rule version to our version, since we might add newly-defined
    // conditions, etc.
    $rule->setConfigVersion($local_version);

    $rule_conditions = $rule->loadConditions();
    $rule_actions = $rule->loadActions();

    $rule->attachConditions($rule_conditions);
    $rule->attachActions($rule_actions);

    $e_name = true;
    $errors = array();
    if ($request->isFormPost() && $request->getStr('save')) {
      $rule->setName($request->getStr('name'));
      $rule->setMustMatchAll(($request->getStr('must_match') == 'all'));

      $repetition_policy_param = $request->getStr('repetition_policy');
      $rule->setRepetitionPolicy(
        HeraldRepetitionPolicyConfig::toInt($repetition_policy_param)
      );

      if (!strlen($rule->getName())) {
        $e_name = "Required";
        $errors[] = "Rule must have a name.";
      }

      $data = json_decode($request->getStr('rule'), true);
      if (!is_array($data) ||
          !$data['conditions'] ||
          !$data['actions']) {
        throw new Exception("Failed to decode rule data.");
      }

      $conditions = array();
      foreach ($data['conditions'] as $condition) {
        if ($condition === null) {
          // We manage this as a sparse array on the client, so may receive
          // NULL if conditions have been removed.
          continue;
        }

        $obj = new HeraldCondition();
        $obj->setFieldName($condition[0]);
        $obj->setFieldCondition($condition[1]);

        if (is_array($condition[2])) {
          $obj->setValue(array_keys($condition[2]));
        } else {
          $obj->setValue($condition[2]);
        }

        $cond_type = $obj->getFieldCondition();

        if ($cond_type == HeraldConditionConfig::CONDITION_REGEXP) {
          if (@preg_match($obj->getValue(), '') === false) {
            $errors[] =
              'The regular expression "'.$obj->getValue().'" is not valid. '.
              'Regular expressions must have enclosing characters (e.g. '.
              '"@/path/to/file@", not "/path/to/file") and be syntactically '.
              'correct.';
          }
        }

        if ($cond_type == HeraldConditionConfig::CONDITION_REGEXP_PAIR) {
          $json = json_decode($obj->getValue(), true);
          if (!is_array($json)) {
            $errors[] =
              'The regular expression pair "'.$obj->getValue().'" is not '.
              'valid JSON. Enter a valid JSON array with two elements.';
          } else {
            if (count($json) != 2) {
              $errors[] =
                'The regular expression pair "'.$obj->getValue().'" must have '.
                'exactly two elements.';
            } else {
              $key_regexp = array_shift($json);
              $val_regexp = array_shift($json);

              if (@preg_match($key_regexp, '') === false) {
                $errors[] =
                  'The first regexp, "'.$key_regexp.'" in the regexp pair '.
                  'is not a valid regexp.';
              }
              if (@preg_match($val_regexp, '') === false) {
                $errors[] =
                  'The second regexp, "'.$val_regexp.'" in the regexp pair '.
                  'is not a valid regexp.';
              }
            }
          }
        }

        $conditions[] = $obj;
      }

      $actions = array();
      foreach ($data['actions'] as $action) {
        if ($action === null) {
          // Sparse on the client; removals can give us NULLs.
          continue;
        }

        $obj = new HeraldAction();
        $obj->setAction($action[0]);

        if (!isset($action[1])) {
          // Legitimate for any action which doesn't need a target, like
          // "Do nothing".
          $action[1] = null;
        }

        if (is_array($action[1])) {
          $obj->setTarget(array_keys($action[1]));
        } else {
          $obj->setTarget($action[1]);
        }

        $actions[] = $obj;
      }

      $rule->attachConditions($conditions);
      $rule->attachActions($actions);

      if (!$errors) {
        try {

// TODO
//          $rule->openTransaction();
            $rule->save();
            $rule->saveConditions($conditions);
            $rule->saveActions($actions);
//          $rule->saveTransaction();

          $uri = '/herald/view/'.$rule->getContentType().'/';

          return id(new AphrontRedirectResponse())
            ->setURI($uri);
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $e_name = "Not Unique";
          $errors[] = "Rule name is not unique. Choose a unique name.";
        }
      }

    }

    $phids = array();
    $phids[] = $rule->getAuthorPHID();

    foreach ($rule->getActions() as $action) {
      if (!is_array($action->getTarget())) {
        continue;
      }
      foreach ($action->getTarget() as $target) {
        $target = (array)$target;
        foreach ($target as $phid) {
          $phids[] = $phid;
        }
      }
    }

    foreach ($rule->getConditions() as $condition) {
      $value = $condition->getValue();
      if (is_array($value)) {
        foreach ($value as $phid) {
          $phids[] = $phid;
        }
      }
    }

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();

    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Form Errors');
      $error_view->setErrors($errors);
    } else {
      $error_view = null;
    }

    $options = array(
      'all' => 'all of',
      'any' => 'any of',
    );

    $selected = $rule->getMustMatchAll() ? 'all' : 'any';

    $must_match = array();
    foreach ($options as $key => $option) {
      $must_match[] = phutil_render_tag(
        'option',
        array(
          'selected' => ($selected == $key) ? 'selected' : null,
          'value' => $key,
        ),
        phutil_escape_html($option));
    }
    $must_match =
      '<select name="must_match">'.
        implode("\n", $must_match).
      '</select>';

    if ($rule->getID()) {
      $action = '/herald/rule/'.$rule->getID().'/';
    } else {
      $action = '/herald/rule/'.$rule->getID().'/';
    }

    // Make the selector for choosing how often this rule should be repeated
    $repetition_selector = "";
    $repetition_policy = HeraldRepetitionPolicyConfig::toString(
      $rule->getRepetitionPolicy()
    );
    $repetition_options = HeraldRepetitionPolicyConfig::getMapForContentType(
      $rule->getContentType()
    );

    if (empty($repetition_options)) {
      // default option is 'every time'
      $repetition_selector = idx(
        HeraldRepetitionPolicyConfig::getMap(),
        HeraldRepetitionPolicyConfig::EVERY
      );
    } else if (count($repetition_options) == 1) {
      // if there's only 1 option, just pick it for the user
      $repetition_selector = reset($repetition_options);
    } else {
      // give the user all the options for this rule type
      $tags = array();

      foreach ($repetition_options as $name => $option) {
        $tags[] = phutil_render_tag(
          'option',
          array (
            'selected'  => ($repetition_policy == $name) ? 'selected' : null,
            'value'     => $name,
          ),
          phutil_escape_html($option)
        );
      }

      $repetition_selector =
        '<select name="repetition_policy">'.
          implode("\n", $tags).
        '</select>';
    }

    require_celerity_resource('herald-css');

    $type_name = $content_type_map[$rule->getContentType()];

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setID('herald-rule-edit-form')
      ->addHiddenInput('type', $rule->getContentType())
      ->addHiddenInput('save', 1)
      ->appendChild(
        // Build this explicitly so we can add a sigil to it.
        javelin_render_tag(
          'input',
          array(
            'type'  => 'hidden',
            'name'  => 'rule',
            'sigil' => 'rule',
          )))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Rule Name')
          ->setName('name')
          ->setError($e_name)
          ->setValue($rule->getName()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Author')
          ->setValue($handles[$rule->getAuthorPHID()]->getName()))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setValue(
            "This rule triggers for <strong>{$type_name}</strong>."))
      ->appendChild(
        '<h1>Conditions</h1>'.
        '<div class="aphront-form-inset">'.
          '<div style="float: right;">'.
            javelin_render_tag(
              'a',
              array(
                'href' => '#',
                'class' => 'button green',
                'sigil' => 'create-condition',
                'mustcapture' => true,
              ),
              'Create New Condition').
          '</div>'.
          '<p>When '.$must_match.' these conditions are met:</p>'.
          '<div style="clear: both;"></div>'.
          javelin_render_tag(
            'table',
            array(
              'sigil' => 'rule-conditions',
              'class' => 'herald-condition-table',
            ),
            '').
        '</div>')
      ->appendChild(
        '<h1>Action</h1>'.
        '<div class="aphront-form-inset">'.
          '<div style="float: right;">'.
          javelin_render_tag(
            'a',
            array(
              'href' => '#',
              'class' => 'button green',
              'sigil' => 'create-action',
              'mustcapture' => true,
            ),
            'Create New Action').
          '</div>'.
          '<p>'.
            'Take these actions '.$repetition_selector.' this rule matches:'.
          '</p>'.
          '<div style="clear: both;"></div>'.
          javelin_render_tag(
            'table',
            array(
              'sigil' => 'rule-actions',
              'class' => 'herald-action-table',
            ),
            '').
        '</div>')
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save Rule')
          ->addCancelButton('/herald/view/'.$rule->getContentType().'/'));

    $serial_conditions = array(
      array('default', 'default', ''),
    );

    if ($rule->getConditions()) {
      $serial_conditions = array();
      foreach ($rule->getConditions() as $condition) {

        $value = $condition->getValue();
        if (is_array($value)) {
          $value_map = array();
          foreach ($value as $k => $fbid) {
            $value_map[$fbid] = $handles[$fbid]->getName();
          }
          $value = $value_map;
        }

        $serial_conditions[] = array(
          $condition->getFieldName(),
          $condition->getFieldCondition(),
          $value,
        );
      }
    }

    $serial_actions = array(
      array('default', ''),
    );
    if ($rule->getActions()) {
      $serial_actions = array();
      foreach ($rule->getActions() as $action) {

        $target_map = array();
        foreach ((array)$action->getTarget() as $fbid) {
          $target_map[$fbid] = $handles[$fbid]->getName();
        }

        $serial_actions[] = array(
          $action->getAction(),
          $target_map,
        );
      }
    }

    $all_rules = id(new HeraldRule())->loadAllWhere(
      'authorPHID = %d AND contentType = %s',
      $rule->getAuthorPHID(),
      $rule->getContentType());
    $all_rules = mpull($all_rules, 'getName', 'getID');
    asort($all_rules);
    unset($all_rules[$rule->getID()]);


    $config_info = array();
    $config_info['fields']
      = HeraldFieldConfig::getFieldMapForContentType($rule->getContentType());
    $config_info['conditions'] = HeraldConditionConfig::getConditionMap();
    foreach ($config_info['fields'] as $field => $name) {
      $config_info['conditionMap'][$field] = array_keys(
        HeraldConditionConfig::getConditionMapForField($field));
    }
    foreach ($config_info['fields'] as $field => $fname) {
      foreach ($config_info['conditions'] as $condition => $cname) {
        $config_info['values'][$field][$condition] =
          HeraldValueTypeConfig::getValueTypeForFieldAndCondition(
            $field,
            $condition);
      }
    }

    $config_info['actions'] =
      HeraldActionConfig::getActionMapForContentType($rule->getContentType());

    foreach ($config_info['actions'] as $action => $name) {
      $config_info['targets'][$action] =
        HeraldValueTypeConfig::getValueTypeForAction($action);
    }

    Javelin::initBehavior(
      'herald-rule-editor',
      array(
        'root' => 'herald-rule-edit-form',
        'conditions' => (object) $serial_conditions,
        'actions' => (object) $serial_actions,
        'template' => $this->buildTokenizerTemplates() + array(
          'rules' => $all_rules,
        ),
        'info' => $config_info,
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Edit Herald Rule');
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => 'Edit Rule',
      ));
  }

  protected function buildTokenizerTemplates() {
    $template = new AphrontTokenizerTemplateView();
    $template = $template->render();

    return array(
      'source' => array(
        'email'       => '/typeahead/common/mailable/',
        'user'        => '/typeahead/common/users/',
        'repository'  => '/typeahead/common/repositories/',
        'package'     => '/typeahead/common/packages/',

/*
        'tag'         => '/datasource/tag/',
*/
      ),
      'markup' => $template,
    );
  }
}
