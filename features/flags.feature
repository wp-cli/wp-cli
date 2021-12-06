Feature: Global flags

  @require-wp-5.5
  Scenario: Setting the URL
    Given a WP installation

    When I run `wp --url=localhost:8001 eval 'echo json_encode( $_SERVER );'`
    Then STDOUT should be JSON containing:
      """
      {
        "HTTP_HOST": "localhost:8001",
        "SERVER_NAME": "localhost",
        "SERVER_PORT": 8001
      }
      """

  @less-than-wp-5.5
  Scenario: Setting the URL
    Given a WP installation

    When I run `wp --url=localhost:8001 eval 'echo json_encode( $_SERVER );'`
    Then STDOUT should be JSON containing:
      """
      {
        "HTTP_HOST": "localhost:8001",
        "SERVER_NAME": "localhost",
        "SERVER_PORT": "8001"
      }
      """

  Scenario: Setting the URL on multisite
    Given a WP multisite installation
    And I run `wp site create --slug=foo`

    When I run `wp --url=example.com/foo option get home`
    Then STDOUT should contain:
      """
      example.com/foo
      """

  @require-wp-3.9
  Scenario: Invalid URL
    Given a WP multisite installation

    When I try `wp post list --url=invalid.example.com`
    Then STDERR should be:
      """
      Error: Site 'invalid.example.com' not found. Verify `--url=<url>` matches an existing site.
      """

  Scenario: Quiet run
    Given a WP installation

    When I try `wp non-existing-command --quiet`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: 'non-existing-command' is not a registered wp command. See 'wp help' for available commands.
      """

  @less-than-php-8
  Scenario: Debug run
    Given a WP installation

    When I try `wp eval 'echo CONST_WITHOUT_QUOTES;'`
    Then STDOUT should be:
      """
      CONST_WITHOUT_QUOTES
      """
    And STDERR should contain:
      """
      Use of undefined constant CONST_WITHOUT_QUOTES
      """
    And the return code should be 0

    When I try `wp eval 'echo CONST_WITHOUT_QUOTES;' --debug`
    Then the return code should be 0
    And STDOUT should be:
      """
      CONST_WITHOUT_QUOTES
      """
    And STDERR should contain:
      """
      Use of undefined constant CONST_WITHOUT_QUOTES
      """

  Scenario: Setting the WP user
    Given a WP installation

    When I run `wp eval 'var_export( is_user_logged_in() );'`
    Then STDOUT should be:
      """
      false
      """

    When I run `wp --user=admin eval 'echo wp_get_current_user()->user_login;'`
    Then STDOUT should be:
      """
      admin
      """

    When I run `wp --user=admin@example.com eval 'echo wp_get_current_user()->user_login;'`
    Then STDOUT should be:
      """
      admin
      """

    When I try `wp --user=non-existing-user eval 'echo wp_get_current_user()->user_login;'`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: Invalid user ID, email or login: 'non-existing-user'
      """

  Scenario: Using a custom logger
    Given an empty directory
    And a custom-logger.php file:
      """
      <?php
      class Dummy_Logger {

        function __call( $method, $args ) {
          echo "log: called '$method' method";
        }
      }

      WP_CLI::set_logger( new Dummy_Logger );
      """

    When I try `wp --require=custom-logger.php is-installed`
    Then STDOUT should contain:
      """
      log: called 'error' method
      """
    And STDERR should be empty
    And the return code should be 1

  Scenario: Using --require
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      /**
       * @when before_wp_load
       */
      class Test_Command extends WP_CLI_Command {

        function req( $args, $assoc_args ) {
          WP_CLI::line( $args[0] );
        }
      }

      WP_CLI::add_command( 'test', 'Test_Command' );
      """

    And a foo.php file:
      """
      <?php echo basename(__FILE__) . "\n";
      """

    And a bar.php file:
      """
      <?php echo basename(__FILE__) . "\n";
      """

    And a wp-cli.yml file:
      """
      require:
        - foo.php
        - bar.php
      """

    And a wp-cli2.yml file:
      """
      require: custom-cmd.php
      """

    When I run `wp --require=custom-cmd.php test req 'This is a custom command.'`
    Then STDOUT should be:
      """
      foo.php
      bar.php
      This is a custom command.
      """

    When I run `WP_CLI_CONFIG_PATH=wp-cli2.yml wp test req 'This is a custom command.'`
    Then STDOUT should contain:
      """
      This is a custom command.
      """

  Scenario: Using --require with globs
    Given an empty directory
    And a foober/foo.php file:
      """
      <?php echo basename(__FILE__) . "\n";
      """
    And a foober/bar.php file:
      """
      <?php echo basename(__FILE__) . "\n";
      """
    And a doobie/doo.php file:
      """
      <?php echo basename(__FILE__) . "\n";
      """

    And a wp-cli.yml file:
      """
      require: foober/*.php
      """

    When I run `wp`
    Then STDOUT should contain:
      """
      bar.php
      foo.php
      """
    When I run `wp --require=doobie/*.php`
    Then STDOUT should contain:
      """
      doo.php
      """

  Scenario: Enabling/disabling color
    Given a WP installation

    When I try `wp --no-color non-existent-command`
    Then STDERR should be:
      """
      Error: 'non-existent-command' is not a registered wp command. See 'wp help' for available commands.
      """

    When I try `wp --color non-existent-command`
    Then STDERR should strictly contain:
      """
      [31;1mError:
      """

  Scenario: Use `WP_CLI_STRICT_ARGS_MODE` to distinguish between global and local args
    Given an empty directory
    And a cmd.php file:
      """
      <?php
      /**
       * @when before_wp_load
       *
       * [--url=<url>]
       * : URL passed to the callback.
       */
      $cmd_test = function( $args, $assoc_args ) {
          $url = WP_CLI::get_runner()->config['url'] ? ' ' . WP_CLI::get_runner()->config['url'] : '';
          WP_CLI::log( 'global:' . $url );
          $url = isset( $assoc_args['url'] ) ? ' ' . $assoc_args['url'] : '';
          WP_CLI::log( 'local:' . $url );
      };
      WP_CLI::add_command( 'cmd-test', $cmd_test );
      """
    And a wp-cli.yml file:
      """
      require:
        - cmd.php
      """

    When I run `wp cmd-test --url=foo.dev`
    Then STDOUT should be:
      """
      global: foo.dev
      local:
      """

    When I run `WP_CLI_STRICT_ARGS_MODE=1 wp cmd-test --url=foo.dev`
    Then STDOUT should be:
      """
      global:
      local: foo.dev
      """

    When I run `WP_CLI_STRICT_ARGS_MODE=1 wp --url=bar.dev cmd-test --url=foo.dev`
    Then STDOUT should be:
      """
      global: bar.dev
      local: foo.dev
      """

  Scenario: Using --http=<url> requires wp-cli/restful
    Given an empty directory

    When I try `wp --http=foo.dev`
    Then STDERR should be:
      """
      Error: RESTful WP-CLI needs to be installed. Try 'wp package install wp-cli/restful'.
      """

  Scenario: Strict args mode should be passed on to ssh
    When I try `WP_CLI_STRICT_ARGS_MODE=1 wp --debug --ssh=/ --version`
    Then STDERR should contain:
      """
      Running SSH command: ssh -q -T '' 'WP_CLI_STRICT_ARGS_MODE=1 wp
      """

  Scenario: SSH flag should support changing directories
    When I try `wp --debug --ssh=wordpress:/my/path --version`
    Then STDERR should contain:
      """
      Running SSH command: ssh -q -T 'wordpress' 'cd '\''/my/path'\''; wp
      """

  Scenario: SSH flag should support Docker
    When I try `wp --debug --ssh=docker:user@wordpress --version`
    Then STDERR should contain:
      """
      Running SSH command: docker exec --user 'user' 'wordpress' sh -c
      """
