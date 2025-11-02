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

    When I run `wp foobar foo --prompt --help`
    Then STDOUT should contain:
      """
      wp foobar
      """
    And STDERR should be empty

  Scenario: Prompt should skip arguments that are already provided
    Given an empty directory
    And a cmd.php file:
      """
      <?php
      /**
       * Test that the flag prompt is case insensitive.
       *
       * ## OPTIONS
       *
       * <arg1>
       * : A positional arg.
       *
       * <arg2>
       * : A positional arg.
       *
       * [--flag1=<value>]
       * : A flag.
       *
       * [--flag2=<value>]
       * : A flag.
       *
       * [--flag3=<value>]
       * : A flag.
       *
       * @when before_wp_load
       */
      WP_CLI::add_command( 'test-prompt', function( $args, $assoc_args ) {
        WP_CLI::line( 'arg1: ' . $args[0] );
        WP_CLI::line( 'arg2: ' . $args[1] );
        WP_CLI::line( 'flag1: ' . $assoc_args['flag1'] );
        WP_CLI::line( 'flag2: ' . $assoc_args['flag2'] );
        WP_CLI::line( 'flag3: ' . $assoc_args['flag3'] );
      } );
      """
    And a value-file file:
      """
      positional2
      value2
      """
    And a wp-cli.yml file:
      """
      require:
        - cmd.php
      """

    When I run `wp test-prompt positional1 --flag1=value1 --flag3=value3 --prompt < value-file`
    Then the return code should be 0
    And STDERR should be empty
    And STDOUT should contain:
      """
      arg1: positional1
      """
    And STDOUT should contain:
      """
      arg2: positional2
      """
    And STDOUT should contain:
      """
      flag1: value1
      """
    And STDOUT should contain:
      """
      flag2: value2
      """
    And STDOUT should contain:
      """
      flag3: value3
      """

  Scenario: Prompt should show full command after inputs
    Given a WP installation
    And a value-file file:
      """
      post_type
      post


      post_title,post_name,post_status
      csv
      """
    When I run `wp post create --post_title='Publish post' --post_content='Publish post content' --post_status='publish'`
    Then STDOUT should not be empty

    When I run `wp post create --post_title='Publish post 2' --post_content='Publish post content' --post_status='publish'`
    Then STDOUT should not be empty

    When I run `wp post list --prompt < value-file`
    Then STDOUT should contain:
      """
      wp post list --post_type='post' --fields='post_title,post_name,post_status' --format='csv'
      """
    And STDOUT should contain:
      """
      post_title,post_name,post_status
      """
    And STDOUT should contain:
      """
      "Publish post 2",publish-post-2,publish
      """
    And STDOUT should contain:
      """
      "Publish post",publish-post,publish
      """
    And STDOUT should contain:
      """
      "Hello world!",hello-world,publish
      """

  Scenario: Prompt should show positional arguments
    Given a WP installation
    And a value-file file:
      """
      category
      General
      general



      """

    When I run `wp term create --prompt < value-file`
    Then STDOUT should contain:
      """
      wp term create 'category' 'General' --slug='general'
      """
    And STDOUT should contain:
      """
      Created category
      """
