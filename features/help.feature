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

  Scenario: Help for nonexistent commands
    Given a WP install
    
    When I try `wp help non-existent-command`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: 'non-existent-command' is not a registered wp command.
      """
      
    When I try `wp help non-existent-command non-existent-subcommand`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: 'non-existent-command non-existent-subcommand' is not a registered wp command.
      """

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

    When I run `wp help`
    Then STDOUT should contain:
      """
      A dummy command.
      """

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
