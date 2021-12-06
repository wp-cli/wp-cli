Feature: Run a WP-CLI command

  Background:
    Given an empty directory
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
       * [--exit_error]
       * : Exit on error.
       *
       * [--return[=<return>]]
       * : Capture and return output.
       *
       * [--parse=<format>]
       * : Parse returned output as a particular format.
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
    Given a WP installation

    When I run `wp <flag> run 'option get home'`
    Then STDOUT should be:
      """
      https://example.com
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
    Given a WP installation

    When I run `wp run <flag> --return 'option get home'`
    Then STDOUT should be:
      """
      returned: 'https://example.com'
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

  Scenario Outline: Use 'parse=json' to parse JSON output
    Given a WP installation

    When I run `wp run --return --parse=json <flag> 'user get admin --fields=user_login,user_email --format=json'`
    Then STDOUT should be:
      """
      returned: array (
        'user_login' => 'admin',
        'user_email' => 'admin@example.com',
      )
      """

    Examples:
      | flag        |
      | --no-launch |
      | --launch    |

  Scenario Outline: Exit on error by default
    Given a WP installation

    When I try `wp run <flag> 'eval "WP_CLI::error( var_export( get_current_user_id(), true ) );"'`
    Then STDOUT should be empty
    And STDERR should be:
      """
      Error: 1
      """
    And the return code should be 1

    Examples:
      | flag        |
      | --no-launch |
      | --launch    |

  Scenario Outline: Override erroring on exit
    Given a WP installation

    When I try `wp run <flag> --no-exit_error --return=all 'eval "WP_CLI::error( var_export( get_current_user_id(), true ) );"'`
    Then STDOUT should be:
      """
      returned: array (
        'stdout' => '',
        'stderr' => 'Error: 1',
        'return_code' => 1,
      )
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp <flag> --no-exit_error run 'option pluck foo$bar barfoo'`
    Then STDOUT should be:
      """
      returned: NULL
      """
    And STDERR should be empty
    And the return code should be 0

    Examples:
      | flag        |
      | --no-launch |
      | --launch    |

  Scenario Outline: Output using echo and log, success, warning and error
    Given a WP installation

    # Note WP_CLI::error() terminates eval processing so needs to be last.
    When I run `wp run <flag> --no-exit_error --return=all 'eval "WP_CLI::log( '\'log\'' ); echo '\'echo\''; WP_CLI::success( '\'success\'' ); WP_CLI::error( '\'error\'' );"'`
    Then STDOUT should be:
      """
      returned: array (
        'stdout' => 'log
      echoSuccess: success',
        'stderr' => 'Error: error',
        'return_code' => 1,
      )
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp run <flag> --no-exit_error --return=all 'eval "echo '\'echo\''; WP_CLI::log( '\'log\'' ); WP_CLI::warning( '\'warning\''); WP_CLI::success( '\'success\'' );"'`
    Then STDOUT should be:
      """
      returned: array (
        'stdout' => 'echolog
      Success: success',
        'stderr' => 'Warning: warning',
        'return_code' => 0,
      )
      """
    And STDERR should be empty
    And the return code should be 0

    Examples:
      | flag        |
      | --no-launch |
      | --launch    |

    @less-than-php-8
  Scenario Outline: Installed packages work as expected
    Given a WP installation

    # Allow for composer/ca-bundle using `openssl_x509_parse()` which throws PHP warnings on old versions of PHP.
    When I try `wp package install wp-cli/scaffold-package-command`
    And I run `wp <flag> run 'help scaffold package'`
    Then STDOUT should contain:
      """
      wp scaffold package <name>
      """
    And STDERR should be empty

    Examples:
    | flag        |
    | --no-launch |
    | --launch    |

  Scenario Outline: Persists global parameters when supplied interactively
    Given a WP installation in 'foo'

    When I run `wp <flag> --path=foo run 'config set test 42 --type=constant'`
    Then STDOUT should be:
      """
      Success: Added the constant 'test' to the 'wp-config.php' file with the value '42'.
      returned: NULL
      """
    And STDERR should be empty
    And the return code should be 0

    Examples:
    | flag        |
    | --no-launch |
    | --launch    |

  Scenario Outline: Apply backwards compat conversions
    Given a WP installation

    When I run `wp <flag> run 'term url category 1'`
    Then STDOUT should be:
      """
      https://example.com/?cat=1
      returned: NULL
      """
    And STDERR should be empty
    And the return code should be 0

    Examples:
    | flag        |
    | --no-launch |
    | --launch    |

  Scenario Outline: Check that proc_open() and proc_close() aren't disabled for launch
    Given a WP installation

    When I try `{INVOKE_WP_CLI_WITH_PHP_ARGS--ddisable_functions=<func>} --launch run 'option get home'`
    Then STDERR should contain:
      """
      Error: Cannot do 'launch option': The PHP functions `proc_open()` and/or `proc_close()` are disabled
      """
    And the return code should be 1

    Examples:
      | func       |
      | proc_open  |
      | proc_close |
