Feature: Utilities that do NOT depend on WordPress code

  Scenario Outline: Check that `proc_open()` and `proc_close()` aren't disabled for `Utils\run_mysql_command()`
    When I try `{INVOKE_WP_CLI_WITH_PHP_ARGS--ddisable_functions=<func>} --skip-wordpress eval 'WP_CLI\Utils\run_mysql_command( null, array() );'`
    Then STDERR should contain:
      """
      Error: Cannot do 'run_mysql_command': The PHP functions `proc_open()` and/or `proc_close()` are disabled
      """
    And STDOUT should be empty
    And the return code should be 1

    Examples:
      | func       |
      | proc_open  |
      | proc_close |

  Scenario Outline: Check that `proc_open()` and `proc_close()` aren't disabled for `Utils\launch_editor_for_input()`
    When I try `{INVOKE_WP_CLI_WITH_PHP_ARGS--ddisable_functions=<func>} --skip-wordpress eval 'WP_CLI\Utils\launch_editor_for_input( null, null );'`
    Then STDERR should contain:
      """
      Error: Cannot do 'launch_editor_for_input': The PHP functions `proc_open()` and/or `proc_close()` are disabled
      """
    And STDOUT should be empty
    And the return code should be 1

    Examples:
      | func       |
      | proc_open  |
      | proc_close |

  Scenario: Check that `Utils\run_mysql_command()` uses STDOUT and STDERR by default
    When I run `wp db query 'SELECT "column_data" as column_name;'`
    Then STDOUT should contain:
      """
      column_name
      """
    And STDOUT should contain:
      """
      column_data
      """
    And STDERR should be empty

    When I run `wp db query 'broken query'`
    Then STDOUT should be empty
    And STDERR should contain:
      """
      You have an error in your SQL syntax
      """

  Scenario: Check that `Utils\run_mysql_command()` can return data and errors if requested
    When I run `wp eval 'WP_CLI\Utils\run_mysql_command( "/usr/bin/env mysql --no-defaults", [], "SHOW DATABASES;" );'`
    Then STDOUT should contain:
      """
      Database
      """
    And STDOUT should contain:
      """
      wp_cli_test
      """
    And STDERR should be empty

    When I run `wp eval '$stdout = ""; WP_CLI\Utils\run_mysql_command( "/usr/bin/env mysql --no-defaults", [], "SHOW DATABASES;", $stdout ); echo str_to_upper( $stdout )'`
    Then STDOUT should contain:
      """
      DATABASE
      """
    And STDOUT should contain:
      """
      WP_CLI_TEST
      """
    And STDERR should be empty

    When I run `wp eval '$stderr = ""; WP_CLI\Utils\run_mysql_command( "/usr/bin/env mysql --no-defaults", [], "broken query", null, $stderr ); echo str_to_upper( $stderr )'`
    Then STDOUT should be empty
    And STDERR should contain:
      """
      YOU HAVE AN ERROR IN YOUR SQL SYNTAX
      """

  # INI directive `sys_temp_dir` introduced PHP 5.5.0.
  @require-php-5.5
  Scenario: Check `Utils\get_temp_dir()` when `sys_temp_dir` directive set
    # `sys_temp_dir` set to unwritable.
    When I try `{INVOKE_WP_CLI_WITH_PHP_ARGS--dsys_temp_dir=\\tmp\\} --skip-wordpress eval 'echo WP_CLI\Utils\get_temp_dir();'`
    Then STDERR should contain:
      """
      Warning: Temp directory isn't writable
      """
    And STDERR should contain:
      """
      \tmp/
      """
    And STDOUT should be:
      """
      \tmp/
      """
    And the return code should be 0

    # `sys_temp_dir` unset.
    When I run `{INVOKE_WP_CLI_WITH_PHP_ARGS--dsys_temp_dir=} --skip-wordpress eval 'echo WP_CLI\Utils\get_temp_dir();'`
    Then STDOUT should match /\/$/
