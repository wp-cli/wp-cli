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

  Scenario: Help runs properly at various points of a functional WP install
    Given an empty directory

    When I run `wp help db import`
    Then STDOUT should contain:
      """
      wp db import
      """

    When I run `wp core download`
    Then STDOUT should not be empty
    And the wp-config-sample.php file should exist

    When I run `wp help db import`
    Then STDOUT should contain:
      """
      wp db import
      """

    When I run `wp core config {CORE_CONFIG_SETTINGS}`
    Then STDOUT should not be empty
    And the wp-config.php file should exist

    When I run `wp help db import`
    Then STDOUT should contain:
      """
      wp db import
      """

    When I run `wp db create`
    Then STDOUT should not be empty

    When I run `wp help db import`
    Then STDOUT should contain:
      """
      wp db import
      """
