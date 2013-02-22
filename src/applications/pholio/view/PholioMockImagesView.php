<?php

final class PholioMockImagesView extends AphrontView {

  private $mock;

  public function setMock(PholioMock $mock) {
    $this->mock = $mock;
    return $this;
  }

  public function render() {
    if (!$this->mock) {
      throw new Exception("Call setMock() before render()!");
    }

    $main_image_id = celerity_generate_unique_node_id();
    require_celerity_resource('javelin-behavior-pholio-mock-view');
    $config = array(
      'mainID' => $main_image_id,
      'mockID' => $this->mock->getID());
    Javelin::initBehavior('pholio-mock-view', $config);

    $mockview = '';

    $main_image = head($this->mock->getImages());

    $main_image_tag = javelin_tag(
      'img',
      array(
        'id' => $main_image_id,
        'src' => $main_image->getFile()->getBestURI(),
        'sigil' => 'mock-image',
        'class' => 'pholio-mock-image',
        'meta' => array(
          'fullSizeURI' => $main_image->getFile()->getBestURI(),
          'imageID' => $main_image->getID(),
        ),
    ));

    $main_image_tag = javelin_tag(
      'div',
      array(
        'id' => 'mock-wrapper',
        'sigil' => 'mock-wrapper',
        'class' => 'pholio-mock-wrapper'
      ),
      $main_image_tag);

    $inline_comments_holder = javelin_tag(
      'div',
      array(
        'id' => 'mock-inline-comments',
        'sigil' => 'mock-inline-comments',
        'class' => 'pholio-mock-inline-comments'
      ),
      '');

    $mockview[] = phutil_tag(
      'div',
        array(
          'class' => 'pholio-mock-image-container',
          'id' => 'pholio-mock-image-container'
        ),
      array($main_image_tag, $inline_comments_holder));

    if (count($this->mock->getImages()) > 1) {
      $thumbnails = array();
      foreach ($this->mock->getImages() as $image) {
        $thumbfile = $image->getFile();

        $dimensions = PhabricatorImageTransformer::getPreviewDimensions(
          $thumbfile,
          140);

        $tag = phutil_tag(
          'img',
          array(
            'width' => $dimensions['sdx'],
            'height' => $dimensions['sdy'],
            'src' => $thumbfile->getPreview140URI(),
            'class' => 'pholio-mock-carousel-thumbnail',
            'style' => 'top: '.floor((140 - $dimensions['sdy'] ) / 2).'px',
        ));

        $thumbnails[] = javelin_tag(
          'div',
          array(
            'sigil' => 'mock-thumbnail',
            'class' => 'pholio-mock-carousel-thumb-item',
            'meta' => array(
              'fullSizeURI' => $thumbfile->getBestURI(),
              'imageID' => $image->getID()
            ),
          ),
          $tag);
      }

      $mockview[] = phutil_tag(
        'div',
        array(
          'class' => 'pholio-mock-carousel',
        ),
        $thumbnails);
    }

    return $this->renderSingleView($mockview);
  }
}
