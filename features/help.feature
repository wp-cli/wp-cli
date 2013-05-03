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
      usage: wp core
      """

    When I run `wp help core download`
    Then it should run without errors
    And STDOUT should contain:
      """
      WP-CORE-DOWNLOAD(1)
      """

    When I run `wp help non-existent-command`
    Then the return code should be 1

  Scenario: Getting help for a third-party command
    Given a WP install
    And a wp-content/plugins/test-cli-help.php file:
      """
      <?php
      // Plugin Name: Test CLI Help

      class Test_Help extends WP_CLI_Command {
        function __invoke() {}
      }

      WP_CLI::add_command( 'test-help', 'Test_Help' );
      """
    And I run `wp plugin activate test-cli-help`
    And it should run without errors

    When I run `wp help test-help`
    Then it should run without errors
    And STDOUT should contain:
      """
      usage: wp test-help
      """
