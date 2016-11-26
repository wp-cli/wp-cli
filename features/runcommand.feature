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
        $ret = is_object( $ret ) ? (array) $ret : $ret;
        WP_CLI::log( 'returned: ' . var_export( $ret, true ) );
      });
      """
    And a wp-cli.yml file:
      """
      user: admin
      require:
        - command.php
      """
    And a config.yml file:
      """
      user get:
        0: admin
        field: user_email
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

    When I run `WP_CLI_CONFIG_PATH=config.yml wp <flag> run 'user get'`
    Then STDOUT should be:
      """
      admin@example.com
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

    When I run `wp <flag> --return=stderr run 'eval "echo wp_get_current_user()->user_login . PHP_EOL;"'`
    Then STDOUT should be:
      """
      returned: ''
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp <flag> --return=return_code run 'eval "echo wp_get_current_user()->user_login . PHP_EOL;"'`
    Then STDOUT should be:
      """
      returned: 0
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp <flag> --return=all run 'eval "echo wp_get_current_user()->user_login . PHP_EOL;"'`
    Then STDOUT should be:
      """
      returned: array (
        'stdout' => 'admin',
        'stderr' => '',
        'return_code' => 0,
      )
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `WP_CLI_CONFIG_PATH=config.yml wp --return <flag> run 'user get'`
    Then STDOUT should be:
      """
      returned: 'admin@example.com'
      """
    And STDERR should be empty
    And the return code should be 0

    Examples:
      | flag        |
      | --no-launch |
      | --launch    |

  Scenario Outline: Installed packages work as expected
    When I run `wp package install wp-cli/scaffold-package-command`
    Then STDERR should be empty

    When I run `wp <flag> run 'help scaffold package'`
    Then STDOUT should contain:
      """
      wp scaffold package <name>
      """
    And STDERR should be empty

    Examples:
    | flag        |
    | --no-launch |
    | --launch    |
