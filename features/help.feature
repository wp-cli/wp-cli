Feature: Get help about WP-CLI commands

  Scenario: Help for internal commands
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

    When I run `wp help --help`
    Then STDOUT should contain:
      """
      WP-HELP(1)
      """

    When I try `wp help non-existent-command`
    Then the return code should be 1
    And STDERR should not be empty

  @ronn
  Scenario: Generating help for subcommands
    Given an empty directory
    When I run `wp help --gen option`
    Then STDOUT should be:
      """
      generated option.1
      """

  @ronn
  Scenario: Generating help for multisite-only subcommands
    Given an empty directory
    When I run `wp help --gen site create`
    Then STDOUT should be:
      """
      generated site-create.1
      """

  @ronn
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
      usage: wp test-help
      """

    When I run `wp help --gen test-help`
    Then STDOUT should contain:
      """
      generated test-help.1
      """

    When I run `wp help test-help`
    Then STDOUT should contain:
      """
      WP-TEST-HELP(1)
      """
