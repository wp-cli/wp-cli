Feature: Get help about WP-CLI commands

  Scenario: Empty dir
    Given an empty directory

    When I run `wp help`
    Then it should run without errors
    And STDOUT should contain:
      """
      Available commands:
      """

    When I run `wp help core`
    Then it should run without errors
    And STDOUT should contain:
      """
      usage:
      """

    When I run `wp help core download`
    Then it should run without errors
    And STDOUT should contain:
      """
      WP-CORE-DOWNLOAD(1)
      """
