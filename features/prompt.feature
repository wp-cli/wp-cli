Feature: Prompt user for input

  Scenario: Flag prompt should be case insensitive
    Given an empty directory
    And a cmd.php file:
      """
      <?php
      /**
       * Test that the flag prompt is case insensitive.
       *
       * ## OPTIONS
       *
       * [--flag]
       * : An optional flag
       *
       * @when before_wp_load
       */
      WP_CLI::add_command( 'test-prompt', function( $_, $assoc_args ){
        var_dump( WP_CLI\Utils\get_flag_value( $assoc_args, 'flag' ) );
      });
      """
    And a uppercase-session file:
      """
      Y
      """
    And a lowercase-session file:
      """
      y
      """
    And a wp-cli.yml file:
      """
      require:
        - cmd.php
      """

    When I run `wp test-prompt --prompt < uppercase-session`
    Then STDOUT should contain:
      """
      bool(true)
      """

    When I run `wp test-prompt --prompt < lowercase-session`
    Then STDOUT should contain:
      """
      bool(true)
      """

  Scenario: Prompt should work with it has a value
    Given an empty directory
    And a command-foobar.php file:
      """
      <?php
      /**
       * Test that the flag prompt is case insensitive.
       *
       * ## OPTIONS
       *
       * <arg>
       * : An positional arg.
       *
       * [--flag1=<value>]
       * : An optional flag
       *
       * [--flag2=<value>]
       * : An optional flag
       *
       * @when before_wp_load
       */
      WP_CLI::add_command( 'foobar', function( $_, $assoc_args ) {
        WP_CLI::line( 'arg: ' . $_[0] );
        WP_CLI::line( 'flag1: ' . $assoc_args['flag1'] );
      } );
      """
    And a wp-cli.yml file:
      """
      require:
        - command-foobar.php
      """

    When I run `echo 'bar' | wp foobar foo --prompt=flag1`
    Then the return code should be 0
    And STDERR should be empty
    And STDOUT should contain:
      """
      arg: foo
      """
    And STDOUT should contain:
      """
      flag1: bar
      """
