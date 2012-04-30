

CREATE TABLE {$NAMESPACE}_countdown.countdown_timer (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  authorPHID VARCHAR(64) BINARY NOT NULL,
  datepoint INT UNSIGNED NOT NULL,
  dateCreated INT UNSIGNED NOT NULL,
  dateModified INT UNSIGNED NOT NULL
);
