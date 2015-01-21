Feature: Global flags

  Scenario: Setting the URL
    Given a WP install

    When I run `wp --url=localhost:8001 eval 'echo json_encode( $_SERVER );'`
    Then STDOUT should be JSON containing:
      """
      {
        "HTTP_HOST": "localhost:8001",
        "SERVER_NAME": "localhost",
        "SERVER_PORT": "8001"
      }
      """

  Scenario: Quiet run
    Given a WP install

    When I try `wp non-existing-command --quiet`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: 'non-existing-command' is not a registered wp command. See 'wp help'.
      """

  Scenario: Debug run
    Given a WP install

    When I run `wp eval 'echo CONST_WITHOUT_QUOTES;'`
    Then STDOUT should be:
      """
      CONST_WITHOUT_QUOTES
      """

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
    Given a WP install

    When I run `wp eval 'echo (int) is_user_logged_in();'`
    Then STDOUT should be:
      """
      0
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
    Then STDOUT should be:
      """
      log: called 'error' method
      """

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

  Scenario: Enabling/disabling color
    Given a WP install

    When I try `wp --no-color non-existent-command`
    Then STDERR should be:
      """
      Error: 'non-existent-command' is not a registered wp command. See 'wp help'.
      """

    When I try `wp --color non-existent-command`
    Then STDERR should contain:
      """
      [31;1mError:
      """

  Scenario: Generate completions
    Given an empty directory

    When I run `wp cli completions --line='wp bogus-comand ' --point=100`
    Then STDOUT should be empty

    When I run `wp cli completions --line='wp eva' --point=100`
    Then STDOUT should be:
      """
      eval 
      eval-file 
      """

    When I run `wp cli completions --line='wp core config --dbname=' --point=100`
    Then STDOUT should be empty

    When I run `wp cli completions --line='wp core config --dbname=foo ' --point=100`
    Then STDOUT should not contain:
      """
      --dbname=
      """
    And STDOUT should contain:
      """
      --extra-php 
      """

    When I run `wp cli completions --line='wp media import ' --point=100`
    Then STDOUT should contain:
      """
      <file> 
      """
