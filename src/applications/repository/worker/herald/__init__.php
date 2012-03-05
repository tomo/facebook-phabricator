<?php
/**
 * This file is automatically generated. Lint this module to rebuild it.
 * @generated
 */



phutil_require_module('phabricator', 'applications/audit/constants/status');
phutil_require_module('phabricator', 'applications/audit/editor/comment');
phutil_require_module('phabricator', 'applications/herald/adapter/commit');
phutil_require_module('phabricator', 'applications/herald/config/contenttype');
phutil_require_module('phabricator', 'applications/herald/engine/engine');
phutil_require_module('phabricator', 'applications/herald/storage/rule');
phutil_require_module('phabricator', 'applications/metamta/storage/mail');
phutil_require_module('phabricator', 'applications/owners/storage/packagecommitrelationship');
phutil_require_module('phabricator', 'applications/phid/handle/data');
phutil_require_module('phabricator', 'applications/repository/storage/commitdata');
phutil_require_module('phabricator', 'applications/repository/worker/base');
phutil_require_module('phabricator', 'infrastructure/env');

phutil_require_module('phutil', 'utils');


phutil_require_source('PhabricatorRepositoryCommitHeraldWorker.php');
