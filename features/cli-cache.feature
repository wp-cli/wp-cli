Feature: CLI Cache

  Scenario: Remove all files from cache directory
    Given an empty cache

    When I run `wp core download --path={CACHE_DIR} --version=4.9 --force`
    And I run `wp core download --path={CACHE_DIR} --version=4.9 --force --locale=de_DE`
    Then the {SUITE_CACHE_DIR}/core directory should contain:
      """
      wordpress-4.9-de_DE.tar.gz
      wordpress-4.9-en_US.tar.gz
      """

    When I run `wp cli cache clear`
    Then STDOUT should be:
      """
      Success: Cache cleared.
      """
    And STDERR should be empty
    And the {SUITE_CACHE_DIR}/core directory should not contain:
      """
      wordpress-4.9-de_DE.tar.gz
      """
    And the {SUITE_CACHE_DIR}/core directory should not contain:
      """
      wordpress-4.9-en_US.tar.gz
      """

  Scenario: Using a null device disables the cache without throwing an error
    Given an empty directory
    And a env-var.php file:
      """
      <?php
      putenv( 'WP_CLI_CACHE_DIR=/dev/null' );
      """

    When I run `wp --require=env-var.php core download --path=/tmp/wp-core --version=4.9 --force`
    Then STDERR should be empty


  Scenario: Remove all but newest files from cache directory
    Given an empty cache
    And a file-a-12345.tmp cache file:
      """
      -empty-
      """
    And a file-a-23456.tmp cache file:
      """
      -empty-
      """
    And a file-b-12345.tmp cache file:
      """
      -empty-
      """
    And a file-b-23456.tmp cache file:
      """
      -empty-
      """
    And a file-b-01234.tmp cache file:
      """
      -empty-
      """
    And a file-c-12345.tmp cache file:
      """
      -empty-
      """

    When I run `wp cli cache prune`
    Then STDOUT should be:
      """
      Success: Cache pruned.
      """
    And the {SUITE_CACHE_DIR}/file-a-12345.tmp file should not exist
    And the {SUITE_CACHE_DIR}/file-a-23456.tmp file should exist
    And the {SUITE_CACHE_DIR}/file-b-12345.tmp file should not exist
    And the {SUITE_CACHE_DIR}/file-b-23456.tmp file should exist
    And the {SUITE_CACHE_DIR}/file-b-01234.tmp file should not exist
    And the {SUITE_CACHE_DIR}/file-c-12345.tmp file should exist
