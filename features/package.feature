Feature: Manage WP-CLI packages

  Scenario: Package CRUD
    Given an empty directory

    When I run `wp package browse`
    Then STDOUT should contain:
      """
      wp-cli/server-command
      """

    When I run `wp package install wp-cli/server-command`
    Then STDERR should be empty

    When I run `wp help server`
    Then STDERR should be empty

    When I run `wp package list`
    Then STDOUT should contain:
      """
      wp-cli/server-command
      """

    When I run `wp package uninstall wp-cli/server-command`
    Then STDERR should be empty