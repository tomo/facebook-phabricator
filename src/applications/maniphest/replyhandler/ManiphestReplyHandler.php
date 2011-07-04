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
class ManiphestReplyHandler extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof ManiphestTask)) {
      throw new Exception("Mail receiver is not a ManiphestTask!");
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'T');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('T');
  }

  public function getReplyHandlerDomain() {
    return PhabricatorEnv::getEnvConfig(
      'metamta.maniphest.reply-handler-domain');
  }

  public function getReplyHandlerInstructions() {
    if ($this->supportsReplies()) {
      return "Reply to comment or attach files, or !close, !claim, or ".
             "!unsubscribe.";
    } else {
      return null;
    }
  }

  public function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {

    $task = $this->getMailReceiver();
    $user = $this->getActor();

    $body = $mail->getCleanTextBody();
    $body = trim($body);

    $lines = explode("\n", trim($body));
    $first_line = head($lines);

    $command = null;
    $matches = null;
    if (preg_match('/^!(\w+)/', $first_line, $matches)) {
      $lines = array_slice($lines, 1);
      $body = implode("\n", $lines);
      $body = trim($body);

      $command = $matches[1];
    }

    $xactions = array();

    $files = $mail->getAttachments();
    if ($files) {
      $file_xaction = new ManiphestTransaction();
      $file_xaction->setAuthorPHID($user->getPHID());
      $file_xaction->setTransactionType(ManiphestTransactionType::TYPE_ATTACH);

      $phid_type = PhabricatorPHIDConstants::PHID_TYPE_FILE;
      $new = $task->getAttached();
      foreach ($files as $file_phid) {
        $new[$phid_type][$file_phid] = array();
      }

      $file_xaction->setNewValue($new);
      $xactions[] = $file_xaction;
    }

    $ttype = ManiphestTransactionType::TYPE_NONE;
    $new_value = null;
    switch ($command) {
      case 'close':
        $ttype = ManiphestTransactionType::TYPE_STATUS;
        $new_value = ManiphestTaskStatus::STATUS_CLOSED_RESOLVED;
        break;
      case 'claim':
        $ttype = ManiphestTransactionType::TYPE_OWNER;
        $new_value = $user->getPHID();
        break;
      case 'unsubscribe':
        $ttype = ManiphestTransactionType::TYPE_CCS;
        $ccs = $task->getCCPHIDs();
        foreach ($ccs as $k => $phid) {
          if ($phid == $user->getPHID()) {
            unset($ccs[$k]);
          }
        }
        $new_value = array_values($ccs);
        break;
    }

    $xaction = new ManiphestTransaction();
    $xaction->setAuthorPHID($user->getPHID());
    $xaction->setTransactionType($ttype);
    $xaction->setNewValue($new_value);
    $xaction->setComments($body);

    $xactions[] = $xaction;

    $editor = new ManiphestTransactionEditor();
    $editor->setParentMessageID($mail->getMessageID());
    $editor->applyTransactions($task, $xactions);
  }

}
