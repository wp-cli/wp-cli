Feature: Framework autoloader takes priority over package autoloaders

  Scenario: Verify framework autoloader is prepended
    Given a WP installation
    And a test-command.php file:
      """
      <?php
      /**
       * Test command to check autoloader order
       *
       * @when before_wp_load
       */
      class Test_Autoloader_Command extends WP_CLI_Command {
        public function check() {
          $autoloaders = spl_autoload_functions();
          $count = count( $autoloaders );
          WP_CLI::log( "Total autoloaders registered: {$count}" );
          
          foreach ( $autoloaders as $index => $loader ) {
            if ( is_array( $loader ) && isset( $loader[0] ) ) {
              $class = is_object( $loader[0] ) ? get_class( $loader[0] ) : $loader[0];
              $method = isset( $loader[1] ) ? $loader[1] : '';
              WP_CLI::log( "Autoloader {$index}: {$class}::{$method}" );
              
              // Check if this is WP_CLI's autoloader
              if ( $class === 'WP_CLI\\Autoloader' ) {
                WP_CLI::success( "WP_CLI\\Autoloader found at position {$index}" );
                return;
              }
            }
          }
          
          WP_CLI::error( "WP_CLI\\Autoloader not found in registered autoloaders" );
        }
      }
      WP_CLI::add_command( 'test-autoloader', 'Test_Autoloader_Command' );
      """

    When I run `wp --require=test-command.php test-autoloader check`
    Then STDOUT should contain:
      """
      WP_CLI\Autoloader found at position
      """
    And STDOUT should contain:
      """
      Success:
      """

  Scenario: Old framework class should not break cmd-dump
    Given a WP installation
    And a wp-content/old-dispatcher/WP_CLI/Dispatcher/RootCommand.php file:
      """
      <?php
      namespace WP_CLI\Dispatcher;

      // Old version without get_hook() - this should NOT be loaded
      class RootCommand {
        public function __construct() {
          throw new \Exception( 'OLD RootCommand loaded - autoloader priority failed!' );
        }
      }
      """
    And a composer.json file:
      """
      {
        "autoload": {
          "psr-4": {
            "WP_CLI\\": "wp-content/old-dispatcher/WP_CLI/"
          }
        }
      }
      """
    And I run `composer dump-autoload 2>&1`

    When I run `wp cli cmd-dump`
    Then STDOUT should contain:
      """
      "name":"wp"
      """
    And STDOUT should contain:
      """
      "hook":""
      """
    And STDERR should not contain:
      """
      OLD RootCommand loaded
      """

  Scenario: Framework classes work correctly with get_hook() method
    Given a WP installation
    And a test-hook.php file:
      """
      <?php
      /**
       * Test command to verify RootCommand has get_hook() method
       *
       * @when before_wp_load
       */
      class Test_Hook_Command extends WP_CLI_Command {
        public function check() {
          $root = WP_CLI::get_root_command();
          
          if ( ! method_exists( $root, 'get_hook' ) ) {
            WP_CLI::error( 'RootCommand does not have get_hook() method' );
          }
          
          $hook = $root->get_hook();
          WP_CLI::log( "RootCommand hook value: '{$hook}'" );
          
          if ( $hook === '' ) {
            WP_CLI::success( 'RootCommand has correct hook value (empty string)' );
          } else {
            WP_CLI::error( "RootCommand hook should be empty string, got: '{$hook}'" );
          }
        }
      }
      WP_CLI::add_command( 'test-hook', 'Test_Hook_Command' );
      """

    When I run `wp --require=test-hook.php test-hook check`
    Then STDOUT should contain:
      """
      RootCommand hook value: ''
      """
    And STDOUT should contain:
      """
      Success: RootCommand has correct hook value (empty string)
      """
