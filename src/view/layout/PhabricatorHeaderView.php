<?php

final class PhabricatorHeaderView extends AphrontView {

  private $objectName;
  private $header;
  private $tags = array();

  public function setHeader($header) {
    $this->header = $header;
    return $this;
  }

  public function setObjectName($object_name) {
    $this->objectName = $object_name;
    return $this;
  }

  public function addTag(PhabricatorTagView $tag) {
    $this->tags[] = $tag;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-header-view-css');

    $header = phutil_escape_html($this->header);

    if ($this->objectName) {
      $header = phutil_tag(
        'a',
        array(
          'href' => '/'.$this->objectName,
        ),
        $this->objectName).' '.$header;
    }

    if ($this->tags) {
      $header .= phutil_render_tag(
        'span',
        array(
          'class' => 'phabricator-header-tags',
        ),
        self::renderSingleView($this->tags));
    }

    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-header-shell',
      ),
      phutil_render_tag(
        'h1',
        array(
          'class' => 'phabricator-header-view',
        ),
        $header));
  }


}
