Feature: Various utilities for WP-CLI commands

  @skip-windows
  Scenario Outline: Check that `proc_open()` and `proc_close()` aren't disabled for `WP_CLI::launch()`
    When I try `{INVOKE_WP_CLI_WITH_PHP_ARGS--ddisable_functions=<func>} --skip-wordpress eval "WP_CLI::launch( null );"`
    Then STDERR should contain:
      """
      Error: Cannot do 'launch': The PHP functions `proc_open()` and/or `proc_close()` are disabled
      """
    And STDOUT should be empty
    And the return code should be 1

    Examples:
      | func       |
      | proc_open  |
      | proc_close |

  @skip-windows
  Scenario: Escape the script path when launching subprocesses
    Given an empty directory
    And a wp-cli-bootstrap.php file:
      """
      <?php
      define( 'WP_CLI_ROOT', '{FRAMEWORK_ROOT}' );
      require_once WP_CLI_ROOT . '/php/wp-cli.php';
      """
    And a launch-subprocesses.php file:
      """
      <?php
      $launch_self_exit_code = WP_CLI::launch_self( 'cli', [ 'version' ], [], false );
      $runcommand_exit_code  = WP_CLI::runcommand(
        'cli version',
        [
          'launch'     => true,
          'exit_error' => false,
          'return'     => 'return_code',
        ]
      );

      WP_CLI::log( "{$launch_self_exit_code},{$runcommand_exit_code}" );
      """

    When I run `cp wp-cli-bootstrap.php "wp cli"`
    And I run `php "wp cli" --skip-wordpress eval-file launch-subprocesses.php`
    Then STDOUT should be:
      """
      0,0
      """
    And STDERR should be empty
    And the return code should be 0

  Scenario: HTTP URL scheme clears pre-existing HTTPS server variable
    Given an empty directory
    And a test.php file:
      """
      <?php
      $_SERVER['HTTPS'] = 'on';
      WP_CLI::set_url( 'http://example.com' );
      echo isset( $_SERVER['HTTPS'] ) ? 'set' : 'not set';
      """

    When I run `wp --skip-wordpress eval-file test.php`
    Then STDOUT should be:
      """
      not set
      """

  Scenario: HTTPS URL scheme sets HTTPS server variable
    Given an empty directory
    And a test.php file:
      """
      <?php
      WP_CLI::set_url( 'https://example.com' );
      echo isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'on' : 'off';
      """

    When I run `wp --skip-wordpress eval-file test.php`
    Then STDOUT should be:
      """
      on
      """
