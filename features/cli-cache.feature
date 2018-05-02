Feature: CLI Cache

  Scenario: Remove all files from cache directory
    Given an empty cache

    When I run `wp cli cache clear`
    Then STDOUT should be:
      """
      Success: Cache cleared.
      """

  Scenario: Remove all but newest files from cache directory
    Given an empty cache

    When I run `wp cli cache prune`
    Then STDOUT should be:
      """
      Success: Cache pruned.
      """
