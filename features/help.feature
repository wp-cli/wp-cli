Feature: Get help about WP-CLI commands

  Scenario: Help for internal commands
    Given an empty directory

    When I run `wp help`
    Then STDOUT should not be empty

    When I run `wp help core`
    Then STDOUT should not be empty

    When I run `wp help core download`
    Then STDOUT should not be empty

    When I run `wp help help`
    Then STDOUT should not be empty

    When I try `wp help non-existent-command`
    Then the return code should be 1
    And STDERR should not be empty

  Scenario: Help for third-party commands
    Given a WP install
    And a wp-content/plugins/test-cli/test-help.txt file:
      """
      ## EXAMPLES

          wp test-help
      """
    And a wp-content/plugins/test-cli/command.php file:
      """
      <?php
      // Plugin Name: Test CLI Help

      class Test_Help extends WP_CLI_Command {
        function __invoke() {}
      }

      WP_CLI::add_command( 'test-help', 'Test_Help' );

      WP_CLI::add_man_dir( __DIR__, __DIR__ );
      """
    And I run `wp plugin activate test-cli`

    When I run `wp help test-help`
    Then STDOUT should contain:
      """
      wp test-help
      """

  Scenario: Help for incomplete commands
    Given an empty directory

    When I run `wp core`
    Then STDOUT should contain:
      """
      usage: wp core
      """
