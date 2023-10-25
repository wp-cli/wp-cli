Feature: Load WP-CLI

  Scenario: A plugin calling wp_signon() shouldn't fatal
    Given a WP installation
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
    Given a WP installation
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
      Error: This does not seem to be a WordPress installation.
      """

  # `wp db create` does not yet work on SQLite,
  # See https://github.com/wp-cli/db-command/issues/234
  @require-mysql
  Scenario: Globalize global variables in wp-config.php
    Given an empty directory
    And WP files
    And a wp-config-extra.php file:
      """
      $redis_server = 'foo';
      """

    When I run `wp config create {CORE_CONFIG_SETTINGS} --skip-check --extra-php < wp-config-extra.php`
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
    Given a WP installation
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

  Scenario: It should be possible to work on a site in maintenance mode
    Given a WP installation
    And a .maintenance file:
      """
      <?php
      $upgrading = time();
      """

    When I run `wp option get home`
    Then STDOUT should be:
      """
      https://example.com
      """

  @require-mysql
  Scenario: Handle error when WordPress cannot connect to the database host
    Given a WP installation
    And a invalid-host.php file:
      """
      <?php
      error_reporting( error_reporting() & ~E_NOTICE );
      define( 'DB_HOST', 'localghost' );
      """

    When I try `wp --require=invalid-host.php option get home`
    Then STDERR should contain:
      """
      Error: Error establishing a database connection.
      """

    When I try `wp --require=invalid-host.php option get home`
    Then STDERR should contain:
      """
      Error: Error establishing a database connection.
      """

  Scenario: Allow WP_CLI hooks to pass arguments to callbacks
    Given an empty directory
    And a my-command.php file:
      """
      <?php

      WP_CLI::add_hook( 'foo', function( $bar ){
        WP_CLI::log( $bar );
      });
      WP_CLI::add_command( 'my-command', function( $args ){
        WP_CLI::do_hook( 'foo', $args[0] );
      }, array( 'when' => 'before_wp_load' ) );
      """

    When I run `wp --require=my-command.php my-command bar`
    Then STDOUT should be:
      """
      bar
      """
    And STDERR should be empty

  Scenario: WP-CLI sets $table_prefix appropriately on multisite
    Given a WP multisite installation
    And I run `wp site create --slug=first`

    When I run `wp eval 'global $table_prefix; echo $table_prefix;'`
    Then STDOUT should be:
      """
      wp_
      """

    When I run `wp eval 'global $blog_id; echo $blog_id;'`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp --url=example.com/first eval 'global $table_prefix; echo $table_prefix;'`
    Then STDOUT should be:
      """
      wp_2_
      """

    When I run `wp --url=example.com/first eval 'global $blog_id; echo $blog_id;'`
    Then STDOUT should be:
      """
      2
      """

  Scenario: Don't apply set_url_scheme because it will always be incorrect
    Given a WP multisite installation
    And I run `wp option update siteurl https://example.com`

    When I run `wp option get siteurl`
    Then STDOUT should be:
      """
      https://example.com
      """

    When I run `wp site list --field=url`
    Then STDOUT should be:
      """
      https://example.com/
      """

  # `wp db reset` does not yet work on SQLite,
  # See https://github.com/wp-cli/db-command/issues/234
  @require-mysql
  Scenario: Show error message when site isn't found and there aren't additional prefixes.
    Given a WP installation
    And I run `wp db reset --yes`

    When I try `wp option get home`
    Then STDERR should be:
      """
      Error: The site you have requested is not installed.
      Run `wp core install` to create database tables.
      """
    And STDOUT should be empty

  Scenario: Show potential table prefixes when site isn't found, single site.
    Given a WP installation
    And "$table_prefix = 'wp_';" replaced with "$table_prefix = 'cli_';" in the wp-config.php file

    When I try `wp option get home`
    Then STDERR should be:
      """
      Error: The site you have requested is not installed.
      Your table prefix is 'cli_'. Found installation with table prefix: wp_.
      Or, run `wp core install` to create database tables.
      """
    And STDOUT should be empty

    # Use try to cater for wp-db errors in old WPs.
    When I try `wp core install --url=example.com --title=example --admin_user=wpcli --admin_email=wpcli@example.com`
    Then STDOUT should contain:
      """
      Success:
      """
    And the return code should be 0

    Given "$table_prefix = 'cli_';" replaced with "$table_prefix = 'test_';" in the wp-config.php file

    When I try `wp option get home`
    Then STDERR should be:
      """
      Error: The site you have requested is not installed.
      Your table prefix is 'test_'. Found installations with table prefix: cli_, wp_.
      Or, run `wp core install` to create database tables.
      """
    And STDOUT should be empty

  # `wp db query` does not yet work on SQLite,
  # See https://github.com/wp-cli/db-command/issues/234
  @require-wp-3.9 @require-mysql
  Scenario: Display a more helpful error message when site can't be found
    Given a WP multisite installation
    And "define( 'DOMAIN_CURRENT_SITE', 'example.com' );" replaced with "define( 'DOMAIN_CURRENT_SITE', 'example.org' );" in the wp-config.php file

    When I try `wp option get home`
    Then STDERR should be:
      """
      Error: Site 'example.org/' not found. Verify DOMAIN_CURRENT_SITE matches an existing site or use `--url=<url>` to override.
      """

    When I try `wp option get home --url=example.io`
    Then STDERR should be:
      """
      Error: Site 'example.io' not found. Verify `--url=<url>` matches an existing site.
      """

    Given "define( 'DOMAIN_CURRENT_SITE', 'example.org' );" replaced with " " in the wp-config.php file
    # WP < 5.0 have bug which will not find a blog given an empty domain unless wp_blogs.domain empty which was (partly) addressed by https://core.trac.wordpress.org/ticket/42299
    # So empty wp_blogs.domain to make behavior consistent across WP versions.
    And I run `wp db query 'UPDATE wp_blogs SET domain = NULL'`

    When I run `cat wp-config.php`
    Then STDOUT should not contain:
      """
      DOMAIN_CURRENT_SITE
      """

    # This will work as finds blog with empty domain and thus uses `home` option.
    # Expect a warning from WP core for PHP 8+.
    When I try `wp option get home`
    Then STDOUT should be:
      """
      https://example.com
      """

    # Undo above.
    Given I run `wp db query 'UPDATE wp_blogs SET domain = "example.com"'`

    When I try `wp option get home --url=example.io`
    Then STDERR should be:
      """
      Error: Site 'example.io' not found. Verify `--url=<url>` matches an existing site.
      """

  Scenario: Don't show 'sitecategories' table unless global terms are enabled
    Given a WP multisite installation

    When I run `wp db tables`
    Then STDOUT should not contain:
      """
      wp_sitecategories
      """

    When I run `wp db tables --network`
    Then STDOUT should not contain:
      """
      wp_sitecategories
      """
