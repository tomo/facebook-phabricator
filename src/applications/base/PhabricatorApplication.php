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
 * @task  info  Application Information
 * @task  ui    UI Integration
 * @task  uri   URI Routing
 * @task  fact  Fact Integration
 * @task  meta  Application Management
 * @group apps
 */
abstract class PhabricatorApplication {


/* -(  Application Information  )-------------------------------------------- */


  public function getName() {
    return substr(get_class($this), strlen('PhabricatorApplication'));
  }

  public function getShortDescription() {
    return $this->getName().' Application';
  }

  public function isEnabled() {
    return true;
  }

  public function getPHID() {
    return 'PHID-APPS-'.get_class($this);
  }

  public function getTypeaheadURI() {
    return $this->getBaseURI();
  }

  public function getBaseURI() {
    return null;
  }

  public function getIconURI() {
    return null;
  }

  public function getAutospriteName() {
    return 'default';
  }

  public function shouldAppearInLaunchView() {
    return true;
  }

  public function getCoreApplicationOrder() {
    return null;
  }

  public function getTitleGlyph() {
    return null;
  }

  public function getHelpURI() {
    // TODO: When these applications get created, link to their docs:
    //
    //  - Drydock
    //  - OAuth Server


    return null;
  }

  public function getEventListeners() {
    return array();
  }


/* -(  URI Routing  )-------------------------------------------------------- */


  public function getRoutes() {
    return array();
  }


/* -(  Fact Integration  )--------------------------------------------------- */


  public function getFactObjectsForAnalysis() {
    return array();
  }


/* -(  UI Integration  )----------------------------------------------------- */


  /**
   * Render status elements (like "3 Waiting Reviews") for application list
   * views. These provide a way to alert users to new or pending action items
   * in applications.
   *
   * @param PhabricatorUser Viewing user.
   * @return list<PhabricatorApplicationStatusView> Application status elements.
   * @task ui
   */
  public function loadStatus(PhabricatorUser $user) {
    return array();
  }


  /**
   * You can provide an optional piece of flavor text for the application. This
   * is currently rendered in application launch views if the application has no
   * status elements.
   *
   * @return string|null Flavor text.
   * @task ui
   */
  public function getFlavorText() {
    return null;
  }


  /**
   * Build items for the main menu.
   *
   * @param  PhabricatorUser    The viewing user.
   * @param  AphrontController  The current controller. May be null for special
   *                            pages like 404, exception handlers, etc.
   * @return list<PhabricatorMainMenuIconView> List of menu items.
   * @task ui
   */
  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {
    return array();
  }


/* -(  Application Management  )--------------------------------------------- */


  public static function getAllInstalledApplications() {
    static $applications;

    if (empty($applications)) {
      $classes = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->setConcreteOnly(true)
        ->selectAndLoadSymbols();

      $apps = array();
      foreach ($classes as $class) {
        $app = newv($class['name'], array());
        if (!$app->isEnabled()) {
          continue;
        }
        $apps[] = $app;
      }
      $applications = $apps;
    }

    return $applications;
  }


}
