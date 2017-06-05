Feature: WP-CLI Commands

  Scenario: Registered WP-CLI commands
    Given an empty directory

    When I run `wp cron`
    Then STDOUT should contain:
      """
      usage: wp cron event <command>
         or: wp cron schedule <command>
         or: wp cron test
      """

    When I run `wp db`
    Then STDOUT should contain:
      """
      or: wp db cli
      """

    When I run `wp export --help`
    Then STDOUT should contain:
      """
      wp export [--dir=<dirname>]
      """

    When I run `wp eval --help`
    Then STDOUT should contain:
      """
      wp eval <php-code>
      """

    When I run `wp import --help`
    Then STDOUT should contain:
      """
      wp import <file>... --authors=<authors>
      """

    When I run `wp media`
    Then STDOUT should contain:
      """
      or: wp media regenerate
      """

    When I run `wp package`
    Then STDOUT should contain:
      """
      or: wp package install
      """

    When I run `wp search-replace --help`
    Then STDOUT should contain:
      """
      wp search-replace <old> <new>
      """

    When I run `wp server --help`
    Then STDOUT should contain:
      """
      wp server [--host=<host>]
      """

    When I run `wp shell --help`
    Then STDOUT should contain:
      """
      wp shell [--basic]
      """

  Scenario: Invalid class is specified for a command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php

      WP_CLI::add_command( 'command example', 'Non_Existent_Class' );
      """

    When I try `wp --require=custom-cmd.php help`
    Then the return code should be 1
    And STDERR should contain:
      """
      Callable "Non_Existent_Class" does not exist, and cannot be registered as `wp command example`.
      """

  Scenario: Invalid subcommand of valid command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      /**
       * @when before_wp_load
       */
      class Custom_Command_Class extends WP_CLI_Command {

          public function valid() {
             WP_CLI::success( 'Hello world' );
          }

      }
      WP_CLI::add_command( 'command', 'Custom_Command_Class' );
      """

    When I try `wp --require=custom-cmd.php command invalid`
    Then STDERR should contain:
      """
      Error: 'invalid' is not a registered subcommand of 'command'. See 'wp help command'.
      """

  Scenario: Use a closure as a command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      /**
       * My awesome closure command
       *
       * <message>
       * : An awesome message to display
       *
       * @when before_wp_load
       */
      $foo = function( $args ) {
        WP_CLI::success( $args[0] );
      };
      WP_CLI::add_command( 'foo', $foo );
      """

    When I run `wp --require=custom-cmd.php help`
    Then STDOUT should contain:
      """
      foo
      """

    When I run `wp --require=custom-cmd.php help foo`
    Then STDOUT should contain:
      """
      My awesome closure command
      """

    When I try `wp --require=custom-cmd.php foo bar --burrito`
    Then STDERR should contain:
      """
      unknown --burrito parameter
      """

    When I run `wp --require=custom-cmd.php foo bar`
    Then STDOUT should contain:
      """
      Success: bar
      """

  Scenario: Use a function as a command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      /**
       * My awesome function command
       *
       * <message>
       * : An awesome message to display
       *
       * @when before_wp_load
       */
      function foo( $args ) {
        WP_CLI::success( $args[0] );
      }
      WP_CLI::add_command( 'foo', 'foo' );
      """

    When I run `wp --require=custom-cmd.php help`
    Then STDOUT should contain:
      """
      foo
      """

    When I run `wp --require=custom-cmd.php help foo`
    Then STDOUT should contain:
      """
      My awesome function command
      """

    When I try `wp --require=custom-cmd.php foo bar --burrito`
    Then STDERR should contain:
      """
      unknown --burrito parameter
      """

    When I run `wp --require=custom-cmd.php foo bar`
    Then STDOUT should contain:
      """
      Success: bar
      """

  Scenario: Use a class method as a command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      class Foo_Class extends WP_CLI_Command {

        public function __construct( $prefix ) {
          $this->prefix = $prefix;
        }
        /**
         * My awesome class method command
         *
         * <message>
         * : An awesome message to display
         *
         * @when before_wp_load
         */
        function foo( $args ) {
          WP_CLI::success( $this->prefix . ':' . $args[0] );
        }
      }
      $foo = new Foo_Class( 'boo' );
      WP_CLI::add_command( 'foo', array( $foo, 'foo' ) );
      """

    When I run `wp --require=custom-cmd.php help`
    Then STDOUT should contain:
      """
      foo
      """

    When I run `wp --require=custom-cmd.php help foo`
    Then STDOUT should contain:
      """
      My awesome class method command
      """

    When I try `wp --require=custom-cmd.php foo bar --burrito`
    Then STDERR should contain:
      """
      unknown --burrito parameter
      """

    When I run `wp --require=custom-cmd.php foo bar`
    Then STDOUT should contain:
      """
      Success: boo:bar
      """

  Scenario: Use a class method as a command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      class Foo_Class extends WP_CLI_Command {
        /**
         * My awesome class method command
         *
         * <message>
         * : An awesome message to display
         *
         * @when before_wp_load
         */
        function foo( $args ) {
          WP_CLI::success( $args[0] );
        }
      }
      WP_CLI::add_command( 'foo', array( 'Foo_Class', 'foo' ) );
      """

    When I run `wp --require=custom-cmd.php help`
    Then STDOUT should contain:
      """
      foo
      """

    When I run `wp --require=custom-cmd.php help foo`
    Then STDOUT should contain:
      """
      My awesome class method command
      """

    When I try `wp --require=custom-cmd.php foo bar --burrito`
    Then STDERR should contain:
      """
      unknown --burrito parameter
      """

    When I run `wp --require=custom-cmd.php foo bar`
    Then STDOUT should contain:
      """
      Success: bar
      """

  Scenario: Use class with __invoke() passed as object
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      class Foo_Class {

        public function __construct( $message ) {
          $this->message = $message;
        }

        /**
         * My awesome class method command
         *
         * @when before_wp_load
         */
        function __invoke( $args ) {
          WP_CLI::success( $this->message );
        }
      }
      $foo = new Foo_Class( 'bar' );
      WP_CLI::add_command( 'instantiated-command', $foo );
      """

    When I run `wp --require=custom-cmd.php instantiated-command`
    Then STDOUT should contain:
      """
      bar
      """
    And STDERR should be empty

  Scenario: Use an invalid class method as a command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      class Foo_Class extends WP_CLI_Command {
        /**
         * My awesome class method command
         *
         * <message>
         * : An awesome message to display
         *
         * @when before_wp_load
         */
        function foo( $args ) {
          WP_CLI::success( $args[0] );
        }
      }
      $foo = new Foo_Class;
      WP_CLI::add_command( 'bar', array( $foo, 'bar' ) );
      """

    When I try `wp --require=custom-cmd.php bar`
    Then STDERR should contain:
      """
      Error: Callable ["Foo_Class","bar"] does not exist, and cannot be registered as `wp bar`.
      """

  Scenario: Register a synopsis for a given command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      function foo( $args, $assoc_args ) {
        $message = array_shift( $args );
        WP_CLI::log( 'Message is: ' . $message );
        WP_CLI::success( $assoc_args['meal'] );
      }
      WP_CLI::add_command( 'foo', 'foo', array(
        'shortdesc'   => 'My awesome function command',
        'when'        => 'before_wp_load',
        'synopsis'    => array(
          array(
            'type'          => 'positional',
            'name'          => 'message',
            'description'   => 'An awesome message to display',
            'optional'      => false,
            'options'       => array( 'hello', 'goodbye' ),
          ),
          array(
            'type'          => 'assoc',
            'name'          => 'apple',
            'description'   => 'A type of fruit.',
            'optional'      => false,
          ),
          array(
            'type'          => 'assoc',
            'name'          => 'meal',
            'description'   => 'A type of meal.',
            'optional'      => true,
            'default'       => 'breakfast',
            'options'       => array( 'breakfast', 'lunch', 'dinner' ),
          ),
        ),
      ) );
      """
    And a wp-cli.yml file:
      """
      require:
        - custom-cmd.php
      """

    When I try `wp foo`
    Then STDOUT should contain:
      """
      usage: wp foo <message> --apple=<apple> [--meal=<meal>]
      """

    When I run `wp help foo`
    Then STDOUT should contain:
      """
      My awesome function command
      """
    And STDOUT should contain:
      """
      SYNOPSIS
      """
    And STDOUT should contain:
      """
      wp foo <message> --apple=<apple> [--meal=<meal>]
      """
    And STDOUT should contain:
      """
      OPTIONS
      """
    And STDOUT should contain:
      """
      <message>
          An awesome message to display
          ---
          options:
            - hello
            - goodbye
          ---
      """
    And STDOUT should contain:
      """
      [--meal=<meal>]
          A type of meal.
          ---
          default: breakfast
          options:
            - breakfast
            - lunch
            - dinner
          ---
      """

    When I try `wp foo nana --apple=fuji`
    Then STDERR should contain:
      """
      Error: Invalid value specified for positional arg.
      """

    When I try `wp foo hello --apple=fuji --meal=snack`
    Then STDERR should contain:
      """
      Invalid value specified for 'meal' (A type of meal.)
      """

    When I run `wp foo hello --apple=fuji`
    Then STDOUT should be:
      """
      Message is: hello
      Success: breakfast
      """

    When I run `wp foo hello --apple=fuji --meal=dinner`
    Then STDOUT should be:
      """
      Message is: hello
      Success: dinner
      """

  Scenario: Register a command with default and accepted arguments.
    Given an empty directory
    And a test-cmd.php file:
      """
      <?php
      /**
       * An amazing command for managing burritos.
       *
       * [<bar>]
       * : This is the bar argument.
       * ---
       * default: burrito
       * ---
       *
       * [<shop>...]
       * : This is where you buy burritos.
       * ---
       * options:
       *   - left_coast_siesta
       *   - cha cha cha
       * ---
       *
       * [--burrito=<burrito>]
       * : This is the burrito argument.
       * ---
       * options:
       *   - beans
       *   - veggies
       * ---
       *
       * @when before_wp_load
       */
      $foo = function( $args, $assoc_args ) {
        $out = array(
          'bar'     => isset( $args[0] ) ? $args[0] : '',
          'shop'    => isset( $args[1] ) ? $args[1] : '',
          'burrito' => isset( $assoc_args['burrito'] ) ? $assoc_args['burrito'] : '',
        );
        WP_CLI::print_value( $out, array( 'format' => 'yaml' ) );
      };
      WP_CLI::add_command( 'foo', $foo );
      """

    When I run `wp --require=test-cmd.php foo --help`
    Then STDOUT should contain:
      """
      [<bar>]
          This is the bar argument.
          ---
          default: burrito
          ---
      """
    And STDOUT should contain:
      """
      [--burrito=<burrito>]
          This is the burrito argument.
          ---
          options:
            - beans
            - veggies
          ---
      """

    When I run `wp --require=test-cmd.php foo`
    Then STDOUT should be YAML containing:
      """
      bar: burrito
      shop:
      burrito:
      """
    And STDERR should be empty

    When I run `wp --require=test-cmd.php foo ''`
    Then STDOUT should be YAML containing:
      """
      bar:
      shop:
      burrito:
      """
    And STDERR should be empty

    When I run `wp --require=test-cmd.php foo apple --burrito=veggies`
    Then STDOUT should be YAML containing:
      """
      bar: apple
      shop:
      burrito: veggies
      """
    And STDERR should be empty

    When I try `wp --require=test-cmd.php foo apple --burrito=meat`
    Then STDERR should contain:
      """
      Error: Parameter errors:
       Invalid value specified for 'burrito' (This is the burrito argument.)
      """

    When I try `wp --require=test-cmd.php foo apple --burrito=''`
    Then STDERR should contain:
      """
      Error: Parameter errors:
       Invalid value specified for 'burrito' (This is the burrito argument.)
      """

    When I try `wp --require=test-cmd.php foo apple taco_del_mar`
    Then STDERR should contain:
      """
      Error: Invalid value specified for positional arg.
      """

    When I try `wp --require=test-cmd.php foo apple 'cha cha cha' taco_del_mar`
    Then STDERR should contain:
      """
      Error: Invalid value specified for positional arg.
      """

    When I run `wp --require=test-cmd.php foo apple 'cha cha cha'`
    Then STDOUT should be YAML containing:
      """
      bar: apple
      shop: cha cha cha
      burrito:
      """
    And STDERR should be empty

  Scenario: Register a command with default and accepted arguments, part two
    Given an empty directory
    And a test-cmd.php file:
      """
      <?php
      /**
       * An amazing command for managing burritos.
       *
       * [<burrito>]
       * : This is the bar argument.
       * ---
       * options:
       *   - beans
       *   - veggies
       * ---
       *
       * @when before_wp_load
       */
      $foo = function( $args, $assoc_args ) {
        $out = array(
          'burrito' => isset( $args[0] ) ? $args[0] : '',
        );
        WP_CLI::print_value( $out, array( 'format' => 'yaml' ) );
      };
      WP_CLI::add_command( 'foo', $foo );
      """

    When I run `wp --require=test-cmd.php foo`
    Then STDOUT should be YAML containing:
      """
      burrito:
      """
    And STDERR should be empty

    When I run `wp --require=test-cmd.php foo beans`
    Then STDOUT should be YAML containing:
      """
      burrito: beans
      """
    And STDERR should be empty

    When I try `wp --require=test-cmd.php foo apple`
    Then STDERR should be:
      """
      Error: Invalid value specified for positional arg.
      """

  Scenario: Removing a subcommand should remove it from the index
    Given an empty directory
    And a remove-comment.php file:
      """
      <?php
      WP_CLI::add_hook( 'after_add_command:comment', function () {
        $command = WP_CLI::get_root_command();
        $command->remove_subcommand( 'comment' );
      } );
      """

    When I run `wp`
    Then STDOUT should contain:
      """
      Manage comments.
      """

    When I run `wp --require=remove-comment.php`
    Then STDOUT should not contain:
      """
      Manage comments.
      """

  Scenario: before_invoke should call subcommands
    Given an empty directory
    And a call-invoke.php file:
      """
      <?php
      /**
       * @when before_wp_load
       */
      $before_invoke = function() {
        WP_CLI::success( 'Invoked' );
      };
      $before_invoke_args = array( 'before_invoke' => function() {
        WP_CLI::success( 'before invoke' );
      }, 'after_invoke' => function() {
        WP_CLI::success( 'after invoke' );
      });
      WP_CLI::add_command( 'before invoke', $before_invoke, $before_invoke_args );
      WP_CLI::add_command( 'before-invoke', $before_invoke, $before_invoke_args );
      """

    When I run `wp --require=call-invoke.php before invoke`
    Then STDOUT should contain:
      """
      Success: before invoke
      Success: Invoked
      Success: after invoke
      """

    When I run `wp --require=call-invoke.php before-invoke`
    Then STDOUT should contain:
      """
      Success: before invoke
      Success: Invoked
      Success: after invoke
      """

  Scenario: Default arguments should respect wp-cli.yml
    Given a WP install
    And a wp-cli.yml file:
      """
      post list:
        format: count
      """

    When I run `wp post list`
    Then STDOUT should be a number

  Scenario: Use class passed as object
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      class Foo_Class {

        public function __construct( $message ) {
          $this->message = $message;
        }

        /**
         * My awesome class method command
         *
         * @when before_wp_load
         */
        function message( $args ) {
          WP_CLI::success( $this->message );
        }
      }
      $foo = new Foo_Class( 'bar' );
      WP_CLI::add_command( 'instantiated-command', $foo );
      """

    When I run `wp --require=custom-cmd.php instantiated-command message`
    Then STDOUT should contain:
      """
      bar
      """
    And STDERR should be empty

  Scenario: WP-CLI suggests matching commands when user entry contains typos
    Given a WP install

    When I try `wp clu`
    Then STDERR should contain:
      """
      Did you mean 'cli'?
      """

    When I try `wp cli nfo`
    Then STDERR should contain:
      """
      Did you mean 'info'?
      """

    When I try `wp cli beyondlevenshteinthreshold`
    Then STDERR should not contain:
      """
      Did you mean
      """

  Scenario: WP-CLI suggests matching parameters when user entry contains typos
    Given an empty directory

    When I try `wp cli info --quie`
    Then STDERR should contain:
      """
      Did you mean '--quiet'?
      """

    When I try `wp cli info --forma=json`
    Then STDERR should contain:
      """
      Did you mean '--format'?
      """

  Scenario: Adding a command can be aborted through the hooks system
    Given an empty directory
    And a abort-add-command.php file:
      """
      <?php
      WP_CLI::add_hook( 'before_add_command:test-command-2', function ( $addition ) {
        $addition->abort( 'Testing hooks.' );
      } );

      WP_CLI::add_command( 'test-command-1', function () {} );
      WP_CLI::add_command( 'test-command-2', function () {} );
      """

    When I run `wp --require=abort-add-command.php`
    Then STDOUT should contain:
      """
      test-command-1
      """
    And STDOUT should not contain:
      """
      test-command-2
      """

  Scenario: Adding a command can depend on a previous command having been added before
    Given an empty directory
    And a add-dependent-command.php file:
      """
      <?php
      class TestCommand {
      }

      WP_CLI::add_hook( 'after_add_command:test-command', function () {
        WP_CLI::add_command( 'test-command sub-command', function () {} );
      } );

      WP_CLI::add_command( 'test-command', 'TestCommand' );
      """

    When I run `wp --require=add-dependent-command.php`
    Then STDOUT should contain:
      """
      test-command
      """

    When I run `wp --require=add-dependent-command.php help test-command`
    Then STDOUT should contain:
      """
      sub-command
      """

  Scenario: Command additions can be deferred until their parent is added
    Given an empty directory
    And a add-deferred-command.php file:
      """
      <?php
      class TestCommand {
      }

      WP_CLI::add_command( 'test-command sub-command', function () {} );

      WP_CLI::add_command( 'test-command', 'TestCommand' );
      """

    When I run `wp --require=add-deferred-command.php`
    Then STDOUT should contain:
      """
      test-command
      """

    When I run `wp --require=add-deferred-command.php help test-command`
    Then STDOUT should contain:
      """
      sub-command
      """

  Scenario: Command additions should work as plugins
    Given a WP install
    And a wp-content/plugins/test-cli/command.php file:
      """
      <?php
      // Plugin Name: Test CLI Help

      class TestCommand {
      }

      function test_function() {
        \WP_CLI::success( 'unknown-parent child-command' );
      }

      WP_CLI::add_command( 'unknown-parent child-command', 'test_function' );

      WP_CLI::add_command( 'test-command sub-command', function () { \WP_CLI::success( 'test-command sub-command' ); } );

      WP_CLI::add_command( 'test-command', 'TestCommand' );
      """
    And I run `wp plugin activate test-cli`

    When I run `wp`
    Then STDOUT should contain:
      """
      test-command
      """
    And STDERR should be empty

    When I run `wp help test-command`
    Then STDOUT should contain:
      """
      sub-command
      """
    And STDERR should be empty

    When I run `wp test-command sub-command`
    Then STDOUT should contain:
      """
      Success: test-command sub-command
      """
    And STDERR should be empty

    When I run `wp unknown-parent child-command`
    Then STDOUT should contain:
      """
      Success: unknown-parent child-command
      """
    And STDERR should be empty

  Scenario: Command additions should work as must-use plugins
    Given a WP install
    And a wp-content/mu-plugins/test-cli.php file:
      """
      <?php
      // Plugin Name: Test CLI Help

      class TestCommand {
      }

      function test_function() {
        \WP_CLI::success( 'unknown-parent child-command' );
      }

      WP_CLI::add_command( 'unknown-parent child-command', 'test_function' );

      WP_CLI::add_command( 'test-command sub-command', function () { \WP_CLI::success( 'test-command sub-command' ); } );

      WP_CLI::add_command( 'test-command', 'TestCommand' );
      """

    When I run `wp`
    Then STDOUT should contain:
      """
      test-command
      """
    And STDERR should be empty

    When I run `wp help test-command`
    Then STDOUT should contain:
      """
      sub-command
      """
    And STDERR should be empty

    When I run `wp test-command sub-command`
    Then STDOUT should contain:
      """
      Success: test-command sub-command
      """
    And STDERR should be empty

    When I run `wp unknown-parent child-command`
    Then STDOUT should contain:
      """
      Success: unknown-parent child-command
      """
    And STDERR should be empty

  Scenario: Command additions should work when registered on after_wp_load
    Given a WP install
    And a wp-content/mu-plugins/test-cli.php file:
      """
      <?php
      // Plugin Name: Test CLI Help

      class TestCommand {
      }

      function test_function() {
        \WP_CLI::success( 'unknown-parent child-command' );
      }

      WP_CLI::add_hook( 'after_wp_load', function(){
        WP_CLI::add_command( 'unknown-parent child-command', 'test_function' );

        WP_CLI::add_command( 'test-command sub-command', function () { \WP_CLI::success( 'test-command sub-command' ); } );

        WP_CLI::add_command( 'test-command', 'TestCommand' );
      });
      """

    When I run `wp`
    Then STDOUT should contain:
      """
      test-command
      """
    And STDERR should be empty

    When I run `wp help test-command`
    Then STDOUT should contain:
      """
      sub-command
      """
    And STDERR should be empty

    When I run `wp test-command sub-command`
    Then STDOUT should contain:
      """
      Success: test-command sub-command
      """
    And STDERR should be empty

    When I run `wp unknown-parent child-command`
    Then STDOUT should contain:
      """
      Success: unknown-parent child-command
      """
    And STDERR should be empty
