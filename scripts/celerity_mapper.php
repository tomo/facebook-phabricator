#!/usr/bin/env php
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

$package_spec = array(
  'javelin.pkg.js' => array(
    'javelin-util',
    'javelin-install',
    'javelin-event',
    'javelin-stratcom',
    'javelin-behavior',
    'javelin-request',
    'javelin-vector',
    'javelin-dom',
    'javelin-json',
    'javelin-uri',
  ),
  'typeahead.pkg.js' => array(
    'javelin-typeahead',
    'javelin-typeahead-normalizer',
    'javelin-typeahead-source',
    'javelin-typeahead-preloaded-source',
    'javelin-typeahead-ondemand-source',
    'javelin-tokenizer',
    'javelin-behavior-aphront-basic-tokenizer',
  ),
  'core.pkg.js' => array(
    'javelin-mask',
    'javelin-workflow',
    'javelin-behavior-workflow',
    'javelin-behavior-aphront-form-disable-on-submit',
    'phabricator-keyboard-shortcut-manager',
    'phabricator-keyboard-shortcut',
    'javelin-behavior-phabricator-keyboard-shortcuts',
    'javelin-behavior-refresh-csrf',
    'javelin-behavior-phabricator-watch-anchor',
    'javelin-behavior-phabricator-autofocus',
    'phabricator-paste-file-upload',
    'phabricator-menu-item',
    'phabricator-dropdown-menu',
  ),
  'core.pkg.css' => array(
    'phabricator-core-css',
    'phabricator-core-buttons-css',
    'phabricator-standard-page-view',
    'aphront-dialog-view-css',
    'aphront-form-view-css',
    'aphront-panel-view-css',
    'aphront-side-nav-view-css',
    'aphront-table-view-css',
    'aphront-crumbs-view-css',
    'aphront-tokenizer-control-css',
    'aphront-typeahead-control-css',
    'aphront-list-filter-view-css',

    'phabricator-directory-css',
    'phabricator-jump-nav',
    'phabricator-app-buttons-css',

    'phabricator-remarkup-css',
    'syntax-highlighting-css',
    'aphront-pager-view-css',
    'phabricator-transaction-view-css',
  ),
  'differential.pkg.css' => array(
    'differential-core-view-css',
    'differential-changeset-view-css',
    'differential-revision-detail-css',
    'differential-revision-history-css',
    'differential-table-of-contents-css',
    'differential-revision-comment-css',
    'differential-revision-add-comment-css',
    'differential-revision-comment-list-css',
    'phabricator-object-selector-css',
    'aphront-headsup-action-list-view-css',
    'phabricator-content-source-view-css',
    'differential-local-commits-view-css',
  ),
  'differential.pkg.js' => array(
    'phabricator-drag-and-drop-file-upload',
    'phabricator-shaped-request',

    'javelin-behavior-differential-feedback-preview',
    'javelin-behavior-differential-edit-inline-comments',
    'javelin-behavior-differential-populate',
    'javelin-behavior-differential-show-more',
    'javelin-behavior-differential-diff-radios',
    'javelin-behavior-differential-accept-with-errors',
    'javelin-behavior-differential-comment-jump',
    'javelin-behavior-differential-add-reviewers-and-ccs',
    'javelin-behavior-differential-keyboard-navigation',
    'javelin-behavior-aphront-drag-and-drop',
    'javelin-behavior-aphront-drag-and-drop-textarea',
    'javelin-behavior-phabricator-object-selector',

    'differential-inline-comment-editor',
    'javelin-behavior-differential-dropdown-menus',
    'javelin-behavior-buoyant',
  ),
  'diffusion.pkg.css' => array(
    'diffusion-commit-view-css',
  ),
  'maniphest.pkg.css' => array(
    'maniphest-task-summary-css',
    'maniphest-transaction-detail-css',
    'maniphest-task-detail-css',
    'aphront-attached-file-view-css',
  ),
  'maniphest.pkg.js' => array(
    'javelin-behavior-maniphest-batch-selector',
    'javelin-behavior-maniphest-transaction-controls',
    'javelin-behavior-maniphest-transaction-preview',
    'javelin-behavior-maniphest-transaction-expand',
  ),
);


require_once dirname(__FILE__).'/__init_script__.php';

if ($argc != 2) {
  $self = basename($argv[0]);
  echo "usage: {$self} <webroot>\n";
  exit(1);
}

phutil_require_module('phutil', 'filesystem');
phutil_require_module('phutil', 'filesystem/filefinder');
phutil_require_module('phutil', 'future/exec');
phutil_require_module('phutil', 'parser/docblock');

$root = Filesystem::resolvePath($argv[1]);

echo "Finding static resources...\n";
$files = id(new FileFinder($root))
  ->withType('f')
  ->withSuffix('js')
  ->withSuffix('css')
  ->withFollowSymlinks(true)
  ->setGenerateChecksums(true)
  ->find();

echo "Processing ".count($files)." files";

$resource_hash = PhabricatorEnv::getEnvConfig('celerity.resource-hash');

$file_map = array();
foreach ($files as $path => $hash) {
  echo ".";
  $name = '/'.Filesystem::readablePath($path, $root);
  $file_map[$name] = array(
    'hash' => md5($hash.$name.$resource_hash),
    'disk' => $path,
  );
}
echo "\n";

$runtime_map = array();
$resource_graph = array();
$hash_map = array();

$parser = new PhutilDocblockParser();
foreach ($file_map as $path => $info) {
  $data = Filesystem::readFile($info['disk']);
  $matches = array();
  $ok = preg_match('@/[*][*].*?[*]/@s', $data, $matches);
  if (!$ok) {
    throw new Exception(
      "File {$path} does not have a header doc comment. Encode dependency ".
      "data in a header docblock.");
  }

  list($description, $metadata) = $parser->parse($matches[0]);

  $provides = preg_split('/\s+/', trim(idx($metadata, 'provides')));
  $requires = preg_split('/\s+/', trim(idx($metadata, 'requires')));
  $provides = array_filter($provides);
  $requires = array_filter($requires);

  if (count($provides) > 1) {
    // NOTE: Documentation-only JS is permitted to @provide no targets.
    throw new Exception(
      "File {$path} must @provide at most one Celerity target.");
  }

  $provides = reset($provides);

  $type = 'js';
  if (preg_match('/\.css$/', $path)) {
    $type = 'css';
  }

  $uri = '/res/'.substr($info['hash'], 0, 8).$path;

  $hash_map[$provides] = $info['hash'];

  $resource_graph[$provides] = $requires;

  $runtime_map[$provides] = array(
    'uri'       => $uri,
    'type'      => $type,
    'requires'  => $requires,
    'disk'      => $path,
  );
}

$celerity_resource_graph = new CelerityResourceGraph();
$celerity_resource_graph->addNodes($resource_graph);
$celerity_resource_graph->setResourceGraph($resource_graph);
$celerity_resource_graph->loadGraph();

foreach ($resource_graph as $provides => $requires) {
  $cycle = $celerity_resource_graph->detectCycles($provides);
  if ($cycle) {
    throw new Exception(
      "Cycle detected in resource graph: ". implode($cycle, " => ")
    );
  }
}

$package_map = array();
foreach ($package_spec as $name => $package) {
  $hashes = array();
  $type = null;
  foreach ($package as $symbol) {
    if (empty($hash_map[$symbol])) {
      throw new Exception(
        "Package specification for '{$name}' includes '{$symbol}', but that ".
        "symbol is not defined anywhere.");
    }
    if ($type === null) {
      $type = $runtime_map[$symbol]['type'];
    } else {
      $ntype = $runtime_map[$symbol]['type'];
      if ($type !== $ntype) {
        throw new Exception(
          "Package specification for '{$name}' mixes resources of type ".
          "'{$type}' with resources of type '{$ntype}'. Each package may only ".
          "contain one type of resource.");
      }
    }
    $hashes[] = $symbol.':'.$hash_map[$symbol];
  }
  $key = substr(md5(implode("\n", $hashes)), 0, 8);
  $package_map['packages'][$key] = array(
    'name'    => $name,
    'symbols' => $package,
    'uri'     => '/res/pkg/'.$key.'/'.$name,
    'type'    => $type,
  );
  foreach ($package as $symbol) {
    $package_map['reverse'][$symbol] = $key;
  }
}

ksort($runtime_map);
$runtime_map = var_export($runtime_map, true);
$runtime_map = preg_replace('/\s+$/m', '', $runtime_map);
$runtime_map = preg_replace('/array \(/', 'array(', $runtime_map);

ksort($package_map['packages']);
ksort($package_map['reverse']);
$package_map = var_export($package_map, true);
$package_map = preg_replace('/\s+$/m', '', $package_map);
$package_map = preg_replace('/array \(/', 'array(', $package_map);

$generated = '@'.'generated';
$resource_map = <<<EOFILE
<?php

/**
 * This file is automatically generated. Use 'celerity_mapper.php' to rebuild
 * it.
 * {$generated}
 */

celerity_register_resource_map({$runtime_map}, {$package_map});

EOFILE;

echo "Writing map...\n";
Filesystem::writeFile(
  $root.'/../src/__celerity_resource_map__.php',
  $resource_map);
echo "Done.\n";
