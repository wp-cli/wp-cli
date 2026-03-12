Feature: Multiple flag values support

  Scenario: Command with repeating parameter accepts same flag multiple times
    Given an empty directory
    And a test-cmd.php file:
      """
      <?php
      /**
       * Test command for multiple flag values
       *
       * @when before_wp_load
       */
      class Test_Multi_Command extends WP_CLI_Command {

          /**
           * List items with multiple status filters
           *
           * ## OPTIONS
           *
           * [--status=<status>...]
           * : Filter by status
           * ---
           * options:
           *   - active
           *   - inactive
           *   - pending
           * ---
           *
           * @subcommand list
           */
          public function list_( $args, $assoc_args ) {
              if ( isset( $assoc_args['status'] ) ) {
                  if ( is_array( $assoc_args['status'] ) ) {
                      WP_CLI::success( 'Status filter: ' . implode( ', ', $assoc_args['status'] ) );
                  } else {
                      WP_CLI::success( 'Status filter: ' . $assoc_args['status'] );
                  }
              } else {
                  WP_CLI::success( 'No status filter' );
              }
          }
      }
      WP_CLI::add_command( 'testmulti', 'Test_Multi_Command' );
      """

    When I run `wp --require=test-cmd.php testmulti list`
    Then STDOUT should contain:
      """
      Success: No status filter
      """

    When I run `wp --require=test-cmd.php testmulti list --status=active`
    Then STDOUT should contain:
      """
      Success: Status filter: active
      """

    When I run `wp --require=test-cmd.php testmulti list --status=active --status=inactive`
    Then STDOUT should contain:
      """
      Success: Status filter: active, inactive
      """

    When I run `wp --require=test-cmd.php testmulti list --status=active --status=inactive --status=pending`
    Then STDOUT should contain:
      """
      Success: Status filter: active, inactive, pending
      """

  Scenario: Command without repeating parameter uses last value when flag is repeated
    Given an empty directory
    And a test-single-cmd.php file:
      """
      <?php
      /**
       * Test command for single flag values
       *
       * @when before_wp_load
       */
      class Test_Single_Command extends WP_CLI_Command {

          /**
           * List items with single status filter
           *
           * ## OPTIONS
           *
           * [--status=<status>]
           * : Filter by status
           * ---
           * options:
           *   - active
           *   - inactive
           *   - pending
           * ---
           *
           * @subcommand list
           */
          public function list_( $args, $assoc_args ) {
              if ( isset( $assoc_args['status'] ) ) {
                  WP_CLI::success( 'Status filter: ' . $assoc_args['status'] );
              } else {
                  WP_CLI::success( 'No status filter' );
              }
          }
      }
      WP_CLI::add_command( 'testsingle', 'Test_Single_Command' );
      """

    When I run `wp --require=test-single-cmd.php testsingle list --status=active --status=inactive`
    Then STDOUT should contain:
      """
      Success: Status filter: inactive
      """

  Scenario: Multiple flag values with option validation
    Given an empty directory
    And a test-validation-cmd.php file:
      """
      <?php
      /**
       * Test command for multiple flag values with validation
       *
       * @when before_wp_load
       */
      class Test_Validation_Command extends WP_CLI_Command {

          /**
           * List items with validated status filters
           *
           * ## OPTIONS
           *
           * [--status=<status>...]
           * : Filter by status
           * ---
           * options:
           *   - active
           *   - inactive
           * ---
           *
           * @subcommand list
           */
          public function list_( $args, $assoc_args ) {
              if ( isset( $assoc_args['status'] ) ) {
                  if ( is_array( $assoc_args['status'] ) ) {
                      WP_CLI::success( 'Filters: ' . implode( ', ', $assoc_args['status'] ) );
                  } else {
                      WP_CLI::success( 'Filters: ' . $assoc_args['status'] );
                  }
              }
          }
      }
      WP_CLI::add_command( 'testval', 'Test_Validation_Command' );
      """

    When I run `wp --require=test-validation-cmd.php testval list --status=active --status=inactive`
    Then STDOUT should contain:
      """
      Success: Filters: active, inactive
      """

    When I try `wp --require=test-validation-cmd.php testval list --status=active --status=invalid`
    Then the return code should be 1
    And STDERR should contain:
      """
      Invalid value 'invalid' specified for 'status'
      """

    When I try `wp --require=test-validation-cmd.php testval list --status=invalid --status=active`
    Then the return code should be 1
    And STDERR should contain:
      """
      Invalid value 'invalid' specified for 'status'
      """

  Scenario: Boolean flags use last-wins behavior when repeated
    Given an empty directory
    And a test-boolean-cmd.php file:
      """
      <?php
      /**
       * Test command for boolean flag behavior
       *
       * @when before_wp_load
       */
      class Test_Boolean_Command extends WP_CLI_Command {

          /**
           * Test boolean flags
           *
           * ## OPTIONS
           *
           * [--verbose]
           * : Enable verbose mode
           *
           * @subcommand test
           */
          public function test( $args, $assoc_args ) {
              $verbose = isset( $assoc_args['verbose'] ) ? $assoc_args['verbose'] : 'not set';
              if ( is_bool( $verbose ) ) {
                  WP_CLI::success( 'Verbose: ' . ( $verbose ? 'true' : 'false' ) );
              } else {
                  WP_CLI::success( 'Verbose: ' . $verbose );
              }
          }
      }
      WP_CLI::add_command( 'testbool', 'Test_Boolean_Command' );
      """

    When I run `wp --require=test-boolean-cmd.php testbool test --verbose`
    Then STDOUT should contain:
      """
      Success: Verbose: true
      """

    When I run `wp --require=test-boolean-cmd.php testbool test --no-verbose`
    Then STDOUT should contain:
      """
      Success: Verbose: false
      """

    When I run `wp --require=test-boolean-cmd.php testbool test --verbose --no-verbose`
    Then STDOUT should contain:
      """
      Success: Verbose: false
      """

    When I run `wp --require=test-boolean-cmd.php testbool test --no-verbose --verbose`
    Then STDOUT should contain:
      """
      Success: Verbose: true
      """

    When I run `wp --require=test-boolean-cmd.php testbool test --verbose --verbose --no-verbose --verbose`
    Then STDOUT should contain:
      """
      Success: Verbose: true
      """
