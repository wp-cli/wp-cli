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
