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

  Scenario: Help for commands with magic methods
    Given a WP install
    And a wp-content/plugins/test-cli/command.php file:
      """
      <?php
      // Plugin Name: Test CLI Help

      class Test_Magic_Methods extends WP_CLI_Command {
        /**
         * A dummy command.
         *
         * @subcommand my-command
         */
        function my_command() {}

        /**
         * Magic methods should not appear as commands
         */
        function __construct() {}
        function __destruct() {}
        function __call( $name, $arguments ) {}
        function __get( $key ) {}
        function __set( $key, $value ) {}
        function __isset( $key ) {}
        function __unset( $key ) {}
        function __sleep() {}
        function __wakeup() {}
        function __toString() {}
        function __set_state() {}
        function __clone() {}
        function __debugInfo() {}
      }

      WP_CLI::add_command( 'test-magic-methods', 'Test_Magic_Methods' );
      """
    And I run `wp plugin activate test-cli`

    When I run `wp test-magic-methods`
    Then STDOUT should contain:
      """
      usage: wp test-magic-methods my-command
      """
    And STDOUT should not contain:
      """
      __destruct
      """
