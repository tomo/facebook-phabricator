ALTER TABLE phabricator_differential.differential_revision
  ADD branchName VARCHAR(255) COLLATE utf8_general_ci;

ALTER TABLE phabricator_differential.differential_revision
  ADD arcanistProjectPHID VARCHAR(64) COLLATE utf8_bin;
