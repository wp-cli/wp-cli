Feature: Export a WordPress database

  Scenario: Database exports with random hash applied
    Given a WP install

    When I run `wp db export --porcelain`
    Then STDOUT should contain:
      """
      wp_cli_test-
      """
    And the wp_cli_test.sql file should not exist

  Scenario: Database export to a specified file path
    Given a WP install

    When I run `wp db export wp_cli_test.sql --porcelain`
    Then STDOUT should contain:
      """
      wp_cli_test.sql
      """
    And the wp_cli_test.sql file should exist
