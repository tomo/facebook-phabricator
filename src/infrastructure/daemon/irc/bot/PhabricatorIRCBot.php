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
 * Simple IRC bot which runs as a Phabricator daemon. Although this bot is
 * somewhat useful, it is also intended to serve as a demo of how to write
 * "system agents" which communicate with Phabricator over Conduit, so you can
 * script system interactions and integrate with other systems.
 *
 * NOTE: This is super janky and experimental right now.
 *
 * @group irc
 */
final class PhabricatorIRCBot extends PhabricatorDaemon {

  private $socket;
  private $handlers;

  private $writeBuffer;
  private $readBuffer;

  private $conduit;

  public function run() {

    $argv = $this->getArgv();
    if (count($argv) !== 1) {
      throw new Exception("usage: PhabricatorIRCBot <json_config_file>");
    }

    $json_raw = Filesystem::readFile($argv[0]);
    $config = json_decode($json_raw, true);
    if (!is_array($config)) {
      throw new Exception("File '{$argv[0]}' is not valid JSON!");
    }

    $server   = idx($config, 'server');
    $port     = idx($config, 'port', 6667);
    $join     = idx($config, 'join', array());
    $handlers = idx($config, 'handlers', array());

    $nick     = idx($config, 'nick', 'phabot');

    if (!preg_match('/^[A-Za-z0-9_]+$/', $nick)) {
      throw new Exception(
        "Nickname '{$nick}' is invalid, must be alphanumeric!");
    }

    if (!$join) {
      throw new Exception("No channels to 'join' in config!");
    }

    foreach ($handlers as $handler) {
      $obj = newv($handler, array($this));
      $this->handlers[] = $obj;
    }

    $conduit_uri = idx($config, 'conduit.uri');
    if ($conduit_uri) {
      $conduit_user = idx($config, 'conduit.user');
      $conduit_cert = idx($config, 'conduit.cert');

      $conduit = new ConduitClient($conduit_uri);
      $response = $conduit->callMethodSynchronous(
        'conduit.connect',
        array(
          'client'            => 'PhabricatorIRCBot',
          'clientVersion'     => '1.0',
          'clientDescription' => php_uname('n').':'.$nick,
          'user'              => $conduit_user,
          'certificate'       => $conduit_cert,
        ));

      $this->conduit = $conduit;
    }

    $errno = null;
    $error = null;
    $socket = fsockopen($server, $port, $errno, $error);
    if (!$socket) {
      throw new Exception("Failed to connect, #{$errno}: {$error}");
    }
    $ok = stream_set_blocking($socket, false);
    if (!$ok) {
      throw new Exception("Failed to set stream nonblocking.");
    }

    $this->socket = $socket;

    $this->writeCommand('USER', "{$nick} 0 * :{$nick}");
    $this->writeCommand('NICK', "{$nick}");
    foreach ($join as $channel) {
      $this->writeCommand('JOIN', "{$channel}");
    }

    $this->runSelectLoop();
  }

  private function runSelectLoop() {
    do {
      $this->stillWorking();

      $read = array($this->socket);
      if (strlen($this->writeBuffer)) {
        $write = array($this->socket);
      } else {
        $write = array();
      }
      $except = array();

      $ok = @stream_select($read, $write, $except, $timeout_sec = 1);
      if ($ok === false) {
        throw new Exception(
          "socket_select() failed: ".socket_strerror(socket_last_error()));
      }

      if ($read) {
        do {
          $data = fread($this->socket, 4096);
          if ($data === false) {
            throw new Exception("fread() failed!");
          } else {
            $this->debugLog(true, $data);
            $this->readBuffer .= $data;
          }
        } while (strlen($data));
      }

      if ($write) {
        do {
          $len = fwrite($this->socket, $this->writeBuffer);
          if ($len === false) {
            throw new Exception("fwrite() failed!");
          } else {
            $this->debugLog(false, substr($this->writeBuffer, 0, $len));
            $this->writeBuffer = substr($this->writeBuffer, $len);
          }
        } while (strlen($this->writeBuffer));
      }

      do {
        $routed_message = $this->processReadBuffer();
      } while ($routed_message);

    } while (true);
  }

  private function write($message) {
    $this->writeBuffer .= $message;
    return $this;
  }

  public function writeCommand($command, $message) {
    return $this->write($command.' '.$message."\r\n");
  }

  private function processReadBuffer() {
    $until = strpos($this->readBuffer, "\r\n");
    if ($until === false) {
      return false;
    }

    $message = substr($this->readBuffer, 0, $until);
    $this->readBuffer = substr($this->readBuffer, $until + 2);

    $pattern =
      '/^'.
      '(?:(?P<sender>:(\S+)) )?'. // This may not be present.
      '(?P<command>[A-Z0-9]+) '.
      '(?P<data>.*)'.
      '$/';

    $matches = null;
    if (!preg_match($pattern, $message, $matches)) {
      throw new Exception("Unexpected message from server: {$message}");
    }

    $irc_message = new PhabricatorIRCMessage(
      idx($matches, 'sender'),
      $matches['command'],
      $matches['data']);

    $this->routeMessage($irc_message);

    return true;
  }

  private function routeMessage(PhabricatorIRCMessage $message) {
    foreach ($this->handlers as $handler) {
      try {
        $handler->receiveMessage($message);
      } catch (Exception $ex) {
        phlog($ex);
      }
    }
  }

  public function __destroy() {
    $this->write("QUIT Goodbye.\r\n");
    fclose($this->socket);
  }

  private function debugLog($is_read, $message) {
    echo $is_read ? '<<< ' : '>>> ';
    echo addcslashes($message, "\0..\37\177..\377");
    echo "\n";
  }

  public function getConduit() {
    if (empty($this->conduit)) {
      throw new Exception(
        "This bot is not configured with a Conduit uplink. Set 'conduit.uri', ".
        "'conduit.user' and 'conduit.cert' in the configuration to connect.");
    }
    return $this->conduit;
  }

}
