Feature: Import a WordPress database

  Scenario: Import from database name path by default
    Given a WP install

    When I run `wp db export wp_cli_test.sql`
    Then the wp_cli_test.sql file should exist

    When I run `wp db import`
    Then STDOUT should be:
      """
      Success: Imported from 'wp_cli_test.sql'.
      """
