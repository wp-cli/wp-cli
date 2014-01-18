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
    And a wp-content/plugins/test-cli/command.php file:
      """
      <?php
      // Plugin Name: Test CLI Help

      class Test_Help extends WP_CLI_Command {
        /**
         * A dummy command.
         */
        function __invoke() {}
      }

      WP_CLI::add_command( 'test-help', 'Test_Help' );
      """
    And I run `wp plugin activate test-cli`

    When I run `wp help --enable-plugins=test-cli`
    Then STDOUT should contain:
      """
      A dummy command.
      """

    When I run `wp help test-help --enable-plugins=test-cli`
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
