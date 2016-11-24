Feature: Run a WP-CLI command

  Background:
    Given a WP install
    And a command.php file:
      """
      <?php
      /**
       * Run a WP-CLI command with WP_CLI::runcommand();
       *
       * ## OPTIONS
       *
       * <command>
       * : Command to run, quoted.
       *
       * [--launch]
       * : Launch a new process for the command.
       *
       * [--capture]
       * : Capture and return output.
       */
      WP_CLI::add_command( 'run', function( $args, $assoc_args ){
        $ret = WP_CLI::runcommand( $args[0], $assoc_args );
        WP_CLI::log( 'returned: ' . var_export( $ret, true ) );
      });
      """
    And a wp-cli.yml file:
      """
      user: admin
      require:
        - command.php
      """

  Scenario: Run a WP-CLI command in the same process, rendering output
    When I run `wp run 'option get home' --no-launch`
    Then STDOUT should be:
      """
      http://example.com
      returned: NULL
      """
    And the return code should be 0

    When I run `wp --no-launch run 'eval "echo wp_get_current_user()->user_login . PHP_EOL;"'`
    Then STDOUT should be:
      """
      admin
      returned: NULL
      """
    And the return code should be 0

  Scenario: Run a WP-CLI command in the same process, capturing output
    When I run `wp run --no-launch --capture 'option get home'`
    Then STDOUT should be:
      """
      returned: 'http://example.com'
      """
    And the return code should be 0

    When I run `wp --no-launch --capture run 'eval "echo wp_get_current_user()->user_login . PHP_EOL;"'`
    Then STDOUT should be:
      """
      returned: 'admin'
      """
    And the return code should be 0
