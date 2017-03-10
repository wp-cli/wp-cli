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

    When I run `wp help help`
    Then STDOUT should contain:
      """
      GLOBAL PARAMETERS
      """

    When I run `wp post list --post_type=post --posts_per_page=5 --help`
    Then STDOUT should contain:
      """
      wp post list
      """

  Scenario: Help when WordPress is downloaded but not installed
    Given an empty directory

    When I run `wp core download`
    And I run `wp help core config`
    Then STDOUT should contain:
      """
      wp core config
      """

    When I run `wp core config {CORE_CONFIG_SETTINGS}`
    And I run `wp help core install`
    Then STDOUT should contain:
      """
      wp core install
      """

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

  Scenario: Help for commands loaded into existing namespaces
    Given a WP install
    And a wp-content/plugins/test-cli/command.php file:
      """
      <?php
      // Plugin Name: Test CLI Extra Site Command

      class Test_CLI_Extra_Site_Command extends WP_CLI_Command {

        /**
         * A dummy command.
         *
         * @subcommand my-command
         */
        function my_command() {}

      }

      WP_CLI::add_command( 'site test-extra', 'Test_CLI_Extra_Site_Command' );
      """
    And I run `wp plugin activate test-cli`

    When I run `wp help site`
    Then STDOUT should contain:
      """
      test-extra
      """

  Scenario: Help renders global parameters correctly
    Given a WP install

    When I run `wp help import get`
    Then STDOUT should contain:
      """
      GLOBAL PARAMETERS
      """
    And STDOUT should not contain:
      """
      ## GLOBAL PARAMETERS
      """

    When I run `wp help option get`
    Then STDOUT should contain:
      """
      GLOBAL PARAMETERS
      """
    And STDOUT should not contain:
      """
      ## GLOBAL PARAMETERS
      """

    When I run `wp help option`
    Then STDOUT should contain:
      """
      GLOBAL PARAMETERS
      """
    And STDOUT should not contain:
      """
      ## GLOBAL PARAMETERS
      """

  Scenario: Display alias in man page
    Given a WP install

    When I run `wp help plugin update`
    Then STDOUT should contain:
      """
      ALIAS

        upgrade
      """

    When I run `wp help plugin install`
    Then STDOUT should not contain:
      """
      ALIAS
      """
