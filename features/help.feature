Feature: Get help about WP-CLI commands

  Scenario: Empty dir
    Given an empty directory

    When I run `wp help`
    Then STDOUT should contain:
      """
      Available commands:
      """

    When I run `wp help core`
    Then STDOUT should contain:
      """
      usage: wp core
      """

    When I run `wp help core download`
    Then STDOUT should contain:
      """
      WP-CORE-DOWNLOAD(1)
      """

    When I try `wp help non-existent-command`
    Then the return code should be 1
    And STDERR should not be empty

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

    When I run `wp help test-help`
    Then STDOUT should contain:
      """
      usage: wp test-help
      """
