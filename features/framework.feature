Feature: Load WP-CLI

  Scenario: A plugin calling wp_signon() shouldn't fatal
    Given a WP install
    And I run `wp user create testuser test@example.org --user_pass=testuser`
    And a wp-content/mu-plugins/test.php file:
      """
      <?php
      add_action( 'plugins_loaded', function(){
        wp_signon( array( 'user_login' => 'testuser', 'user_password' => 'testuser' ) );
      });
      """

    When I run `wp option get home`
    Then STDOUT should not be empty

  Scenario: A command loaded before WordPress then calls WordPress to load
    Given a WP install
    And a custom-cmd.php file:
      """
      <?php
      class Load_WordPress_Command_Class extends WP_CLI_Command {

          /**
           * @when before_wp_load
           */
          public function __invoke() {
              if ( ! function_exists( 'update_option' ) ) {
                  WP_CLI::log( 'WordPress not loaded.' );
              }
              WP_CLI::get_runner()->load_wordpress();
              if ( function_exists( 'update_option' ) ) {
                  WP_CLI::log( 'WordPress loaded!' );
              }
              WP_CLI::get_runner()->load_wordpress();
              WP_CLI::log( 'load_wordpress() can safely be called twice.' );
          }

      }
      WP_CLI::add_command( 'load-wordpress', 'Load_WordPress_Command_Class' );
      """

    When I run `wp --require=custom-cmd.php load-wordpress`
    Then STDOUT should be:
      """
      WordPress not loaded.
      WordPress loaded!
      load_wordpress() can safely be called twice.
      """

  Scenario: A command loaded before WordPress then calls WordPress to load, but WP doesn't exist
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      class Load_WordPress_Command_Class extends WP_CLI_Command {

          /**
           * @when before_wp_load
           */
          public function __invoke() {
              if ( ! function_exists( 'update_option' ) ) {
                  WP_CLI::log( 'WordPress not loaded.' );
              }
              WP_CLI::get_runner()->load_wordpress();
              if ( function_exists( 'update_option' ) ) {
                  WP_CLI::log( 'WordPress loaded!' );
              }
              WP_CLI::get_runner()->load_wordpress();
              WP_CLI::log( 'load_wordpress() can safely be called twice.' );
          }

      }
      WP_CLI::add_command( 'load-wordpress', 'Load_WordPress_Command_Class' );
      """

    When I try `wp --require=custom-cmd.php load-wordpress`
    Then STDOUT should be:
      """
      WordPress not loaded.
      """
    And STDERR should contain:
      """
      Error: This does not seem to be a WordPress install.
      """

  Scenario: Globalize global variables in wp-config.php
    Given an empty directory
    And WP files
    And a wp-config-extra.php file:
      """
      $redis_server = 'foo';
      """

    When I run `wp core config {CORE_CONFIG_SETTINGS} --extra-php < wp-config-extra.php`
    Then the wp-config.php file should contain:
      """
      $redis_server = 'foo';
      """

    When I run `wp db create`
    And I run `wp core install --url='localhost:8001' --title='Test' --admin_user=wpcli --admin_email=admin@example.com --admin_password=1`
    Then STDOUT should not be empty

    When I run `wp eval 'echo $GLOBALS["redis_server"];'`
    Then STDOUT should be:
      """
      foo
      """

  Scenario: Use a custom error code with WP_CLI::error()
    Given an empty directory
    And a exit-normal.php file:
      """
      <?php
      WP_CLI::error( 'This is return code 1.' );
      """
    And a exit-higher.php file:
      """
      <?php
      WP_CLI::error( 'This is return code 5.', 5 );
      """
    And a no-exit.php file:
      """
      <?php
      WP_CLI::error( 'This has no exit.', false );
      WP_CLI::error( 'So I can use multiple lines.', false );
      """

    When I try `wp --require=exit-normal.php`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: This is return code 1.
      """

    When I try `wp --require=exit-higher.php`
    Then the return code should be 5
    And STDERR should be:
      """
      Error: This is return code 5.
      """

    When I try `wp --require=no-exit.php`
    Then the return code should be 0
    And STDERR should be:
      """
      Error: This has no exit.
      Error: So I can use multiple lines.
      """

  Scenario: A plugin calling wp_redirect() shouldn't redirect
    Given a WP install
    And a wp-content/mu-plugins/redirect.php file:
      """
      <?php
      add_action( 'init', function(){
          wp_redirect( 'http://apple.com' );
      });
      """

    When I try `wp option get home`
    Then STDERR should contain:
      """
      Warning: Some code is trying to do a URL redirect.
      """
