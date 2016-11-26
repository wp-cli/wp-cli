@require-php-5.4
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
       * [--return[=<return>]]
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

  Scenario Outline: Run a WP-CLI command and render output
    When I run `wp <flag> run 'option get home'`
    Then STDOUT should be:
      """
      http://example.com
      returned: NULL
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp <flag> run 'eval "echo wp_get_current_user()->user_login . PHP_EOL;"'`
    Then STDOUT should be:
      """
      admin
      returned: NULL
      """
    And STDERR should be empty
    And the return code should be 0

    Examples:
      | flag        |
      | --no-launch |
      | --launch    |

  Scenario Outline: Run a WP-CLI command and capture output
    When I run `wp run <flag> --return 'option get home'`
    Then STDOUT should be:
      """
      returned: 'http://example.com'
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp <flag> --return run 'eval "echo wp_get_current_user()->user_login . PHP_EOL;"'`
    Then STDOUT should be:
      """
      returned: 'admin'
      """
    And STDERR should be empty
    And the return code should be 0

    Examples:
      | flag        |
      | --no-launch |
      | --launch    |
