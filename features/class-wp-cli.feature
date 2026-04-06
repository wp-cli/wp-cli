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

  Scenario: HTTP URL scheme clears pre-existing HTTPS server variable
    Given an empty directory
    And a test.php file:
      """
      <?php
      $_SERVER['HTTPS'] = 'on';
      WP_CLI::set_url('http://example.com');
      echo isset($_SERVER['HTTPS']) ? 'set' : 'not set';
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
      WP_CLI::set_url('https://example.com');
      echo isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'on' : 'off';
      """

    When I run `wp --skip-wordpress eval-file test.php`
    Then STDOUT should be:
      """
      on
      """
