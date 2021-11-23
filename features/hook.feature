Feature: Tests `WP_CLI::add_hook()`

  Scenario: Add callback to the `before_invoke:plugin list`
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

  Scenario: Add callback to the `before_invoke:db check`
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

  Scenario: Add callback to the `before_invoke:core version`
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
      $callback = function ( $args, $assoc_args, $options ) {
        WP_CLI::log( '`add_hook()` to the `before_run_command` is working.' );
        if ( 'version' !== $args[1] ) {
          WP_CLI::error( 'Arg context not being passed in to callback properly' );
        }

        if ( ! array_key_exists( 'extra', $assoc_args ) ) {
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

  Scenario: Use return value of a callback hook
    Given a WP installation
    And a custom-hook.php file:
      """
      <?php
      $callback = function ( $first, $second ) {
        WP_CLI::log( '`add_hook()` to the `custom_hook` is working.' );
        if ( 'value1' !== $first ) {
          WP_CLI::error( 'First argument is not being passed in to callback properly' );
        }

        if ( 'value2' !== $second ) {
          WP_CLI::error( 'Second argument is not being passed in to callback properly' );
        }

        return 'value3';
      };

      WP_CLI::add_hook( 'custom_hook', $callback );

      $result = WP_CLI::do_hook( 'custom_hook', 'value1', 'value2' );

      if ( empty( $result ) ) {
        WP_CLI::error( 'First argument is not returned via do_hook()' );
      }

      if ( 'value3' !== $result ) {
        WP_CLI::error( 'First argument is not mutable via do_hook()' );
      }
      """
    And a wp-cli.yml file:
      """
      require:
        - custom-hook.php
      """

    When I run `wp cli version`
    Then STDOUT should contain:
      """
      `add_hook()` to the `custom_hook` is working.
      """
    Then STDOUT should not contain:
      """
      First argument is not being passed in to callback properly
      """
    And STDOUT should not contain:
      """
      Second argument is not being passed in to callback properly
      """
    And STDOUT should not contain:
      """
      First argument is not returned via do_hook()
      """
    And STDOUT should not contain:
      """
      First argument is not mutable via do_hook()
      """
    And the return code should be 0

  Scenario: Callback hook with arguments does not break on bad callback
    Given a WP installation
    And a custom-hook.php file:
      """
      <?php
      $callback = function ( $first, $second ) {
        WP_CLI::log( '`add_hook()` to the `custom_hook` is working.' );
        if ( 'value1' !== $first ) {
          WP_CLI::error( 'First argument is not being passed in to callback properly' );
        }

        if ( 'value2' !== $second ) {
          WP_CLI::error( 'Second argument is not being passed in to callback properly' );
        }
      };

      WP_CLI::add_hook( 'custom_hook', $callback );

      $result = WP_CLI::do_hook( 'custom_hook', 'value1', 'value2' );

      if ( empty( $result ) ) {
        WP_CLI::error( 'First argument is not returned via do_hook()' );
      }

      if ( 'value1' !== $result ) {
        WP_CLI::error( 'First argument is not correctly returned on bad callback missing return' );
      }
      """
    And a wp-cli.yml file:
      """
      require:
        - custom-hook.php
      """

    When I run `wp cli version`
    Then STDOUT should contain:
      """
      `add_hook()` to the `custom_hook` is working.
      """
    Then STDOUT should not contain:
      """
      First argument is not being passed in to callback properly
      """
    And STDOUT should not contain:
      """
      Second argument is not being passed in to callback properly
      """
    And STDOUT should not contain:
      """
      First argument is not returned via do_hook()
      """
    And STDOUT should not contain:
      """
      First argument is not correctly returned on bad callback missing return
      """
    And the return code should be 0
