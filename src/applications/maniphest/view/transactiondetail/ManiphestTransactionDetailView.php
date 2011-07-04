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
 * @group maniphest
 */
class ManiphestTransactionDetailView extends ManiphestView {

  private $transactions;
  private $handles;
  private $markupEngine;
  private $forEmail;
  private $preview;
  private $commentNumber;

  private $renderSummaryOnly;
  private $renderFullSummary;
  private $user;

  public function setTransactionGroup(array $transactions) {
    $this->transactions = $transactions;
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setMarkupEngine(PhutilMarkupEngine $engine) {
    $this->markupEngine = $engine;
    return $this;
  }

  public function setPreview($preview) {
    $this->preview = $preview;
    return $this;
  }

  public function setRenderSummaryOnly($render_summary_only) {
    $this->renderSummaryOnly = $render_summary_only;
    return $this;
  }

  public function getRenderSummaryOnly() {
    return $this->renderSummaryOnly;
  }

  public function setRenderFullSummary($render_full_summary) {
    $this->renderFullSummary = $render_full_summary;
    return $this;
  }

  public function getRenderFullSummary() {
    return $this->renderFullSummary;
  }

  public function setCommentNumber($comment_number) {
    $this->commentNumber = $comment_number;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function renderForEmail($with_date) {
    $this->forEmail = true;

    $transaction = reset($this->transactions);
    $author = $this->renderHandles(array($transaction->getAuthorPHID()));

    $action = null;
    $descs = array();
    $comments = null;
    foreach ($this->transactions as $transaction) {
      list($verb, $desc, $classes) = $this->describeAction($transaction);
      if ($action === null) {
        $action = $verb;
      }
      $desc = $author.' '.$desc.'.';
      if ($with_date) {
        // NOTE: This is going into a (potentially multi-recipient) email so
        // we can't use a single user's timezone preferences. Use the server's
        // instead, but make the timezone explicit.
        $datetime = date('M jS \a\t g:i A T', $transaction->getDateCreated());
        $desc = "On {$datetime}, {$desc}";
      }
      $descs[] = $desc;
      if ($transaction->hasComments()) {
        $comments = $transaction->getComments();
      }
    }

    $descs = implode("\n", $descs);

    if ($comments) {
      $descs .= "\n".$comments;
    }

    foreach ($this->transactions as $transaction) {
      $supplemental = $this->renderSupplementalInfoForEmail($transaction);
      if ($supplemental) {
        $descs .= "\n\n".$supplemental;
      }
    }

    $this->forEmail = false;
    return array($action, $descs);
  }

  public function render() {

    if (!$this->user) {
      throw new Exception("Call setUser() before render()!");
    }

    $handles = $this->handles;
    $transactions = $this->transactions;

    require_celerity_resource('maniphest-transaction-detail-css');

    $comment_transaction = null;
    foreach ($this->transactions as $transaction) {
      if ($transaction->hasComments()) {
        $comment_transaction = $transaction;
        break;
      }
    }
    $any_transaction = reset($transactions);

    $author = $this->handles[$any_transaction->getAuthorPHID()];

    $more_classes = array();
    $descs = array();
    foreach ($transactions as $transaction) {
      list($verb, $desc, $classes) = $this->describeAction($transaction);
      $more_classes = array_merge($more_classes, $classes);
      $full_summary = null;
      if ($this->getRenderFullSummary()) {
        $full_summary = $this->renderFullSummary($transaction);
      }
      $descs[] = javelin_render_tag(
        'div',
        array(
          'sigil' => 'maniphest-transaction-description',
        ),
        $author->renderLink().' '.$desc.'.'.$full_summary);
    }
    $descs = implode("\n", $descs);

    if ($this->getRenderSummaryOnly()) {
      return $descs;
    }

    $more_classes = implode(' ', $more_classes);

    if ($comment_transaction && $comment_transaction->hasComments()) {
      $comments = $comment_transaction->getCache();
      if (!strlen($comments)) {
        $comments = $comment_transaction->getComments();
        if (strlen($comments)) {
          $comments = $this->markupEngine->markupText($comments);
          $comment_transaction->setCache($comments);
          if ($comment_transaction->getID() && !$this->preview) {
            $comment_transaction->save();
          }
        }
      }
      $comment_block =
        '<div class="maniphest-transaction-comments phabricator-remarkup">'.
          $comments.
        '</div>';
    } else {
      $comment_block = null;
    }

    if ($this->preview) {
      $timestamp = 'COMMENT PREVIEW';
    } else {
      $timestamp = phabricator_datetime(
        $transaction->getDateCreated(),
        $this->user);
    }

    $info = array();
    $info[] = $timestamp;

    $comment_anchor = null;
    $num = $this->commentNumber;
    if ($num && !$this->preview) {
      Javelin::initBehavior('phabricator-watch-anchor');
      $info[] = javelin_render_tag(
        'a',
        array(
          'name' => 'comment-'.$num,
          'href' => '#comment-'.$num,
        ),
        'Comment T'.$any_transaction->getTaskID().'#'.$num);
      $comment_anchor = 'anchor-comment-'.$num;
    }

    $info = implode(' &middot; ', $info);

    return phutil_render_tag(
      'div',
      array(
        'class' => "maniphest-transaction-detail-container",
        'style' => "background-image: url('".$author->getImageURI()."')",
        'id'    => $comment_anchor,
      ),
      '<div class="maniphest-transaction-detail-view '.$more_classes.'">'.
        '<div class="maniphest-transaction-header">'.
          '<div class="maniphest-transaction-timestamp">'.
            $info.
          '</div>'.
          $descs.
        '</div>'.
        $comment_block.
      '</div>');
  }

  private function renderSupplementalInfoForEmail($transaction) {
    $handles = $this->handles;

    $type = $transaction->getTransactionType();
    $new = $transaction->getNewValue();
    $old = $transaction->getOldValue();

    switch ($type) {
      case ManiphestTransactionType::TYPE_DESCRIPTION:
        return "NEW DESCRIPTION\n  ".trim($new)."\n\n".
               "PREVIOUS DESCRIPTION\n  ".trim($old);
      case ManiphestTransactionType::TYPE_ATTACH:
        $old_raw = nonempty($old, array());
        $new_raw = nonempty($new, array());

        $attach_types = array(
          PhabricatorPHIDConstants::PHID_TYPE_DREV,
          PhabricatorPHIDConstants::PHID_TYPE_FILE,
        );

        foreach ($attach_types as $type) {
          $old = array_keys(idx($old_raw, $type, array()));
          $new = array_keys(idx($new_raw, $type, array()));
          if ($old != $new) {
            break;
          }
        }

        $added = array_diff($new, $old);
        if (!$added) {
          break;
        }

        $links = array();
        foreach (array_select_keys($handles, $added) as $handle) {
          $links[] = '  '.PhabricatorEnv::getProductionURI($handle->getURI());
        }
        $links = implode("\n", $links);

        switch ($type) {
          case PhabricatorPHIDConstants::PHID_TYPE_DREV:
            $title = 'ATTACHED REVISIONS';
            break;
          case PhabricatorPHIDConstants::PHID_TYPE_FILE:
            $title = 'ATTACHED FILES';
            break;
        }

        return $title."\n".$links;
      default:
        break;
    }

    return null;
  }

  private function describeAction($transaction) {
    $verb = null;
    $desc = null;
    $classes = array();

    $handles = $this->handles;

    $type = $transaction->getTransactionType();
    $author_phid = $transaction->getAuthorPHID();
    $new = $transaction->getNewValue();
    $old = $transaction->getOldValue();
    switch ($type) {
      case ManiphestTransactionType::TYPE_TITLE:
        $verb = 'Retitled';
        $desc = 'changed the title from '.$this->renderString($old).
                                   ' to '.$this->renderString($new);
        break;
      case ManiphestTransactionType::TYPE_DESCRIPTION:
        $verb = 'Edited';
        if ($this->forEmail || $this->getRenderFullSummary()) {
          $desc = 'updated the task description';
        } else {
          $desc = 'updated the task description; '.
                  $this->renderExpandLink($transaction);
        }
        break;
      case ManiphestTransactionType::TYPE_NONE:
        $verb = 'Commented On';
        $desc = 'added a comment';
        break;
      case ManiphestTransactionType::TYPE_OWNER:
        if ($transaction->getAuthorPHID() == $new) {
          $verb = 'Claimed';
          $desc = 'claimed this task';
          $classes[] = 'claimed';
        } else if (!$new) {
          $verb = 'Up For Grabs';
          $desc = 'placed this task up for grabs';
          $classes[] = 'upforgrab';
        } else if (!$old) {
          $verb = 'Assigned';
          $desc = 'assigned this task to '.$this->renderHandles(array($new));
          $classes[] = 'assigned';
        } else {
          $verb = 'Reassigned';
          $desc = 'reassigned this task from '.
                  $this->renderHandles(array($old)).
                  ' to '.
                  $this->renderHandles(array($new));
          $classes[] = 'reassigned';
        }
        break;
      case ManiphestTransactionType::TYPE_CCS:
        if ($this->preview) {
          $verb = 'Changed CC';
          $desc = 'changed CCs..';
          break;
        }
        $added = array_diff($new, $old);
        $removed = array_diff($old, $new);
        if ($added && !$removed) {
          $verb = 'Added CC';
          if (count($added) == 1) {
            $desc = 'added '.$this->renderHandles($added).' to CC';
          } else {
            $desc = 'added CCs: '.$this->renderHandles($added);
          }
        } else if ($removed && !$added) {
          $verb = 'Removed CC';
          if (count($removed) == 1) {
            $desc = 'removed '.$this->renderHandles($removed).' from CC';
          } else {
            $desc = 'removed CCs: '.$this->renderHandles($removed);
          }
        } else {
          $verb = 'Changed CC';
          $desc = 'changed CCs, added: '.$this->renderHandles($added).'; '.
                             'removed: '.$this->renderHandles($removed);
        }
        break;
      case ManiphestTransactionType::TYPE_PROJECTS:
        if ($this->preview) {
          $verb = 'Changed Projects';
          $desc = 'changed projects..';
          break;
        }
        $added = array_diff($new, $old);
        $removed = array_diff($old, $new);
        if ($added && !$removed) {
          $verb = 'Added Project';
          if (count($added) == 1) {
            $desc = 'added project '.$this->renderHandles($added);
          } else {
            $desc = 'added projects: '.$this->renderHandles($added);
          }
        } else if ($removed && !$added) {
          $verb = 'Removed Project';
          if (count($removed) == 1) {
            $desc = 'removed project '.$this->renderHandles($removed);
          } else {
            $desc = 'removed projectss: '.$this->renderHandles($removed);
          }
        } else {
          $verb = 'Changed Projects';
          $desc = 'changed projects, added: '.$this->renderHandles($added).'; '.
                                  'removed: '.$this->renderHandles($removed);
        }
        break;
      case ManiphestTransactionType::TYPE_STATUS:
        if ($new == ManiphestTaskStatus::STATUS_OPEN) {
          if ($old) {
            $verb = 'Reopened';
            $desc = 'reopened this task';
            $classes[] = 'reopened';
          } else {
            $verb = 'Created';
            $desc = 'created this task';
            $classes[] = 'created';
          }
        } else if ($new == ManiphestTaskStatus::STATUS_CLOSED_SPITE) {
          $verb = 'Spited';
          $desc = 'closed this task out of spite';
          $classes[] = 'spited';
        } else if ($new == ManiphestTaskStatus::STATUS_CLOSED_DUPLICATE) {
          $verb = 'Merged';
          $desc = 'closed this task as a duplicate';
          $classes[] = 'duplicate';
        } else {
          $verb = 'Closed';
          $full = idx(ManiphestTaskStatus::getTaskStatusMap(), $new, '???');
          $desc = 'closed this task as "'.$full.'"';
          $classes[] = 'closed';
        }
        break;
      case ManiphestTransactionType::TYPE_PRIORITY:
        $old_name = ManiphestTaskPriority::getTaskPriorityName($old);
        $new_name = ManiphestTaskPriority::getTaskPriorityName($new);

        if ($old == ManiphestTaskPriority::PRIORITY_TRIAGE) {
          $verb = 'Triaged';
          $desc = 'triaged this task as "'.$new_name.'" priority';
        } else if ($old > $new) {
          $verb = 'Lowered Priority';
          $desc = 'lowered the priority of this task from "'.$old_name.'" to '.
                  '"'.$new_name.'"';
        } else {
          $verb = 'Raised Priority';
          $desc = 'raised the priority of this task from "'.$old_name.'" to '.
                  '"'.$new_name.'"';
        }
        if ($new == ManiphestTaskPriority::PRIORITY_UNBREAK_NOW) {
          $classes[] = 'unbreaknow';
        }
        break;
      case ManiphestTransactionType::TYPE_ATTACH:
        if ($this->preview) {
          $verb = 'Changed Attached';
          $desc = 'changed attachments..';
          break;
        }

        $old_raw = nonempty($old, array());
        $new_raw = nonempty($new, array());

        foreach (array(PhabricatorPHIDConstants::PHID_TYPE_DREV,
          PhabricatorPHIDConstants::PHID_TYPE_FILE) as $type) {
          $old = array_keys(idx($old_raw, $type, array()));
          $new = array_keys(idx($new_raw, $type, array()));
          if ($old != $new) {
            break;
          }
        }

        $added = array_diff($new, $old);
        $removed = array_diff($old, $new);

        $add_desc = $this->renderHandles($added);
        $rem_desc = $this->renderHandles($removed);

        switch ($type) {
          case PhabricatorPHIDConstants::PHID_TYPE_DREV:
            $singular = 'Differential Revision';
            $plural = 'Differential Revisions';
            break;
          case PhabricatorPHIDConstants::PHID_TYPE_FILE:
            $singular = 'file';
            $plural = 'files';
            break;
        }

        if ($added && !$removed) {
          $verb = 'Attached';
          if (count($added) == 1) {
            $desc = 'attached '.$singular.': '.$add_desc;
          } else {
            $desc = 'attached '.$plural.': '.$add_desc;
          }
        } else if ($removed && !$added) {
          $verb = 'Detached';
          if (count($removed) == 1) {
            $desc = 'detached '.$singular.': '.$rem_desc;
          } else {
            $desc = 'detached '.$plural.': '.$rem_desc;
          }
        } else {
          $verb = 'Changed Attached';
          $desc = 'changed attached '.$plural.', added: '.$add_desc.
                                              'removed: '.$rem_desc;
        }
        break;
      default:
        return array($type, ' brazenly '.$type."'d", $classes);
    }

    return array($verb, $desc, $classes);
  }

  private function renderFullSummary($transaction) {
    switch ($transaction->getTransactionType()) {
      case ManiphestTransactionType::TYPE_DESCRIPTION:
        $engine = $this->markupEngine;

        $old = $transaction->getOldValue();
        $new = $transaction->getNewValue();
        $table =
          '<table class="maniphest-change-table">
            <tr>
              <th>Previous Description</th>
              <th>New Description</th>
            </tr>
            <tr>
              <td>
                <div class="phabricator-remarkup">'.
                  $engine->markupText($old).
                '</div>
              </td>
              <td>
                <div class="phabricator-remarkup">'.
                  $engine->markupText($new).
                '</div>
              </td>
            </tr>
          </table>';

        return $table;
    }

    return null;
  }

  private function renderExpandLink($transaction) {
    $id = $transaction->getID();

    Javelin::initBehavior('maniphest-transaction-expand');

    return javelin_render_tag(
      'a',
      array(
        'href'          => '/maniphest/task/descriptionchange/'.$id.'/',
        'sigil'         => 'maniphest-expand-transaction',
        'mustcapture'   => true,
      ),
      'show details');
  }

  private function renderHandles($phids) {
    $links = array();
    foreach ($phids as $phid) {
      if ($this->forEmail) {
        $links[] = $this->handles[$phid]->getName();
      } else {
        $links[] = $this->handles[$phid]->renderLink();
      }
    }
    return implode(', ', $links);
  }

  private function renderString($string) {
    if ($this->forEmail) {
      return '"'.$string.'"';
    } else {
      return '"'.phutil_escape_html($string).'"';
    }
  }

}
