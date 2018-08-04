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

  Scenario: Remove all but newest files from cache directory
    Given an empty cache

    When I run `wp cli cache prune`
    Then STDOUT should be:
      """
      Success: Cache pruned.
      """
