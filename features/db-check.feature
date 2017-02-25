Feature: Check the database

  Scenario: Run db check to check the database
    Given a WP install

    When I run `wp db check`
    Then STDOUT should contain:
      """
      wp_cli_test.wp_users
      """
    And STDOUT should contain:
      """
      Success: Database checked.
      """
    And STDERR should be empty
