<?php

final class PhabricatorFileListController extends PhabricatorFileController {

  private $filter;

  private function setFilter($filter) {
    $this->filter = $filter;
    return $this;
  }

  private function getFilter() {
    return $this->filter;
  }

  public function willProcessRequest(array $data) {
    $this->setFilter(idx($data, 'filter', 'my'));
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($request);

    $query = id(new PhabricatorFileQuery())
      ->setViewer($user);

    switch ($this->getFilter()) {
      case 'my':
        $query->withAuthorPHIDs(array($user->getPHID()));
        $header = pht('Files You Uploaded');
        break;
      case 'all':
      default:
        $header = pht('All Files');
        break;
    }

    $files = $query->executeWithCursorPager($pager);
    $this->loadHandles(mpull($files, 'getAuthorPHID'));

    $highlighted = $request->getStrList('h');
    $file_list = $this->buildFileList($files, $highlighted);

    $side_nav = $this->buildSideNavView();
    $side_nav->selectFilter($this->getFilter());

    $header_view = id(new PhabricatorHeaderView())
      ->setHeader($header);

    $side_nav->appendChild(
      array(
        $header_view,
        $file_list,
        $pager,
        new PhabricatorGlobalUploadTargetView(),
      ));

    $side_nav->setCrumbs(
      $this
        ->buildApplicationCrumbs()
        ->addCrumb(
          id(new PhabricatorCrumbView())
            ->setName($header)
            ->setHref($request->getRequestURI())));

    return $this->buildApplicationPage(
      $side_nav,
      array(
        'title' => 'Files',
        'device' => true,
      ));
  }

  private function buildFileList(array $files, array $highlighted_ids) {
    assert_instances_of($files, 'PhabricatorFile');

    $request = $this->getRequest();
    $user = $request->getUser();

    $highlighted_ids = array_fill_keys($highlighted_ids, true);

    $list_view = id(new PhabricatorObjectItemListView())
      ->setViewer($user);

    foreach ($files as $file) {
      $id = $file->getID();
      $phid = $file->getPHID();
      $name = $file->getName();

      $file_name = "F{$id} {$name}";
      $file_uri = $this->getApplicationURI("/info/{$phid}/");

      $date_created = phabricator_date($file->getDateCreated(), $user);

      $author_phid = $file->getAuthorPHID();
      if ($author_phid) {
        $author_link = $this->getHandle($author_phid)->renderLink();
        $uploaded = pht('Uploaded by %s on %s', $author_link, $date_created);
      } else {
        $uploaded = pht('Uploaded on %s', $date_created);
      }

      $item = id(new PhabricatorObjectItemView())
        ->setObject($file)
        ->setHeader($file_name)
        ->setHref($file_uri)
        ->addAttribute($uploaded)
        ->addIcon('none', phabricator_format_bytes($file->getByteSize()));

      if (isset($highlighted_ids[$id])) {
        $item->setEffect('highlighted');
      }

      $list_view->addItem($item);
    }

    return $list_view;
  }

}
