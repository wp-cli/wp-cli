Feature: Manage WP-CLI packages

  Scenario: Empty dir
    Given an empty directory

    When I run `wp package browse`
    Then STDOUT should contain:
      """
      wp-cli/server-command
      """