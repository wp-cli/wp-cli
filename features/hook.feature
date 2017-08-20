Feature: Tests `WP_CLI::add_hook()`

  Scenario: Add callback to the `before_invoke`
    Given a WP install
    And a wp-content/mu-plugins/test-harness.php file:
      """
      <?php
      $callback = function() {
        WP_CLI::log( '`add_hook()` to the `before_invoke` is working.');
      };

      WP_CLI::add_hook( 'before_invoke:plugin list', $callback );
      WP_CLI::add_hook( 'before_invoke:theme list', $callback );
      WP_CLI::add_hook( 'before_invoke:db check', $callback );
      """

    When I run `wp plugin list`
    Then STDOUT should contain:
      """
      `add_hook()` to the `before_invoke` is working.
      """
    And the return code should be 0

    When I run `wp theme list`
    Then STDOUT should contain:
      """
      `add_hook()` to the `before_invoke` is working.
      """
    And the return code should be 0

    When I run `wp db check`
    Then STDOUT should contain:
      """
      `add_hook()` to the `before_invoke` is working.
      """
    And the return code should be 0
