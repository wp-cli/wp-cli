Feature: Manage WP-CLI packages

  Scenario: Package CRUD
    Given an empty directory

    When I run `wp package browse`
    Then STDOUT should contain:
      """
      danielbachhuber/wp-cli-reset-post-date-command
      """

    When I run `wp package install danielbachhuber/wp-cli-reset-post-date-command`
    Then STDERR should be empty

    When I run `wp help reset-post-date`
    Then STDERR should be empty

    When I run `wp package list`
    Then STDOUT should contain:
      """
      danielbachhuber/wp-cli-reset-post-date-command
      """

    When I run `wp package uninstall danielbachhuber/wp-cli-reset-post-date-command`
    Then STDERR should be empty
