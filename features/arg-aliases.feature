Feature: Argument aliases support

  Scenario: Short-form alias for a parameter
    Given a WP install
    And a custom-command.php file:
      """
      <?php
      /**
       * Test command with argument aliases.
       *
       * ## OPTIONS
       *
       * [--with-dependencies]
       * : Include dependencies in the operation.
       * ---
       * alias: w
       * ---
       */
      $test_command = function( $args, $assoc_args ) {
        if ( isset( $assoc_args['with-dependencies'] ) ) {
          WP_CLI::success( 'with-dependencies is set' );
        } else {
          WP_CLI::error( 'with-dependencies is not set' );
        }
      };
      WP_CLI::add_command( 'test-alias', $test_command );
      """

    When I run `wp --require=custom-command.php test-alias --with-dependencies`
    Then STDOUT should contain:
      """
      Success: with-dependencies is set
      """

    When I run `wp --require=custom-command.php test-alias -w`
    Then STDOUT should contain:
      """
      Success: with-dependencies is set
      """

  Scenario: Multiple aliases for same parameter
    Given a WP install
    And a custom-command.php file:
      """
      <?php
      /**
       * Test command with multiple aliases.
       *
       * ## OPTIONS
       *
       * [--verbose]
       * : Enable verbose output.
       * ---
       * alias:
       *   - v
       *   - debug
       * ---
       */
      $test_command = function( $args, $assoc_args ) {
        if ( isset( $assoc_args['verbose'] ) ) {
          WP_CLI::success( 'verbose is set' );
        } else {
          WP_CLI::error( 'verbose is not set' );
        }
      };
      WP_CLI::add_command( 'test-alias', $test_command );
      """

    When I run `wp --require=custom-command.php test-alias --verbose`
    Then STDOUT should contain:
      """
      Success: verbose is set
      """

    When I run `wp --require=custom-command.php test-alias -v`
    Then STDOUT should contain:
      """
      Success: verbose is set
      """

    When I run `wp --require=custom-command.php test-alias --debug`
    Then STDOUT should contain:
      """
      Success: verbose is set
      """

  Scenario: Alias with value parameter
    Given a WP install
    And a custom-command.php file:
      """
      <?php
      /**
       * Test command with value parameter alias.
       *
       * ## OPTIONS
       *
       * [--number=<number>]
       * : A number value.
       * ---
       * alias: n
       * ---
       */
      $test_command = function( $args, $assoc_args ) {
        if ( isset( $assoc_args['number'] ) ) {
          WP_CLI::success( 'number is ' . $assoc_args['number'] );
        } else {
          WP_CLI::error( 'number is not set' );
        }
      };
      WP_CLI::add_command( 'test-alias', $test_command );
      """

    When I run `wp --require=custom-command.php test-alias --number=42`
    Then STDOUT should contain:
      """
      Success: number is 42
      """

    When I run `wp --require=custom-command.php test-alias -n=42`
    Then STDOUT should contain:
      """
      Success: number is 42
      """

  Scenario: Long-form alias for parameter
    Given a WP install
    And a custom-command.php file:
      """
      <?php
      /**
       * Test command with long-form alias.
       *
       * ## OPTIONS
       *
       * [--include-deps]
       * : Include dependencies.
       * ---
       * alias: with-dependencies
       * ---
       */
      $test_command = function( $args, $assoc_args ) {
        if ( isset( $assoc_args['include-deps'] ) ) {
          WP_CLI::success( 'include-deps is set' );
        } else {
          WP_CLI::error( 'include-deps is not set' );
        }
      };
      WP_CLI::add_command( 'test-alias', $test_command );
      """

    When I run `wp --require=custom-command.php test-alias --include-deps`
    Then STDOUT should contain:
      """
      Success: include-deps is set
      """

    When I run `wp --require=custom-command.php test-alias --with-dependencies`
    Then STDOUT should contain:
      """
      Success: include-deps is set
      """

  Scenario: Canonical name takes precedence when both provided
    Given a WP install
    And a custom-command.php file:
      """
      <?php
      /**
       * Test command where both canonical and alias provided.
       *
       * ## OPTIONS
       *
       * [--format=<format>]
       * : Output format.
       * ---
       * alias: f
       * ---
       */
      $test_command = function( $args, $assoc_args ) {
        WP_CLI::success( 'format is ' . $assoc_args['format'] );
      };
      WP_CLI::add_command( 'test-alias', $test_command );
      """

    When I run `wp --require=custom-command.php test-alias --format=json -f=xml`
    Then STDOUT should contain:
      """
      Success: format is json
      """

  Scenario: Alias resolves before validation
    Given a WP install
    And a custom-command.php file:
      """
      <?php
      /**
       * Test command with required parameter alias.
       *
       * ## OPTIONS
       *
       * --type=<type>
       * : Required type parameter.
       * ---
       * alias: t
       * ---
       */
      $test_command = function( $args, $assoc_args ) {
        WP_CLI::success( 'type is ' . $assoc_args['type'] );
      };
      WP_CLI::add_command( 'test-alias', $test_command );
      """

    When I try `wp --require=custom-command.php test-alias`
    Then STDERR should contain:
      """
      missing --type parameter
      """
    And the return code should be 1

    When I run `wp --require=custom-command.php test-alias --type=post`
    Then STDOUT should contain:
      """
      Success: type is post
      """

    When I run `wp --require=custom-command.php test-alias -t=post`
    Then STDOUT should contain:
      """
      Success: type is post
      """
