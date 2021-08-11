Feature: Tests `WP_CLI::add_hook()`

  Scenario: Add callback to the `before_invoke`
    Given a WP installation
    And a before-invoke.php file:
      """
      <?php
      $callback = function() {
        WP_CLI::log( '`add_hook()` to the `before_invoke` is working.');
      };

      WP_CLI::add_hook( 'before_invoke:plugin list', $callback );
      """
    And a wp-cli.yml file:
      """
      require:
        - before-invoke.php
      """

    When I run `wp plugin list`
    Then STDOUT should contain:
      """
      `add_hook()` to the `before_invoke` is working.
      """
    And the return code should be 0

  Scenario: Add callback to the `before_invoke`
    Given a WP installation
    And a before-invoke.php file:
      """
      <?php
      $callback = function() {
        WP_CLI::log( '`add_hook()` to the `before_invoke` is working.');
      };

      WP_CLI::add_hook( 'before_invoke:db check', $callback );
      """
    And a wp-cli.yml file:
      """
      require:
        - before-invoke.php
      """

    When I run `wp db check`
    Then STDOUT should contain:
      """
      `add_hook()` to the `before_invoke` is working.
      """
    And the return code should be 0

  Scenario: Add callback to the `before_invoke`
    Given a WP installation
    And a before-invoke.php file:
      """
      <?php
      $callback = function() {
        WP_CLI::log( '`add_hook()` to the `before_invoke` is working.');
      };

      WP_CLI::add_hook( 'before_invoke:core version', $callback );
      """
    And a wp-cli.yml file:
      """
      require:
        - before-invoke.php
      """

    When I run `wp core version`
    Then STDOUT should contain:
      """
      `add_hook()` to the `before_invoke` is working.
      """
    And the return code should be 0

  Scenario: Add callback to the `before_run_command` with args
    Given a WP installation
    And a before-run-command.php file:
      """
      <?php
      $callback = function( $args, $assoc_args, $options ) {
        WP_CLI::log( '`add_hook()` to the `before_run_command` is working.');
        if ( 'version' !== $args[0] ) {
          WP_CLI::error( 'Arg context not being passed in to callback properly' );
        }

        if ( ! array_key_exists( 'extra', $assoc_args ) {
          WP_CLI::error( 'Assoc arg context not being passed in to callback properly' );
        }
      };

      WP_CLI::add_hook( 'before_run_command', $callback );
      """
    And a wp-cli.yml file:
      """
      require:
        - before-run-command.php
      """

    When I run `wp core version --extra`
    Then STDOUT should contain:
      """
      `add_hook()` to the `before_run_command` is working.
      """
    And the return code should be 0
