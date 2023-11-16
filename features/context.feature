Feature: Context handling via --context global flag

  Scenario: CLI context can be selected, but is same as default
    Given a WP install

    When I run `wp eval 'var_export( is_admin() );'`
    Then the return code should be 0
    And STDOUT should be:
      """
      false
      """

    When I run `wp --context=cli eval 'var_export( is_admin() );'`
    Then the return code should be 0
    And STDOUT should be:
      """
      false
      """

    When I run `wp eval 'var_export( function_exists( "media_handle_upload" ) );'`
    Then the return code should be 0
    And STDOUT should be:
      """
      true
      """

    When I run `wp --context=cli eval 'var_export( function_exists( "media_handle_upload" ) );'`
    Then the return code should be 0
    And STDOUT should be:
      """
      true
      """

    When I run `wp eval 'add_action( "admin_init", static function () { WP_CLI::warning( "admin_init was triggered." ); } );'`
    Then the return code should be 0
    And STDERR should not contain:
      """
      admin_init was triggered.
      """

    When I run `wp --context=cli eval 'add_action( "admin_init", static function () { WP_CLI::warning( "admin_init was triggered." ); } );'`
    Then the return code should be 0
    And STDERR should not contain:
      """
      admin_init was triggered.
      """

  Scenario: Admin context can be selected
    Given a WP install

    When I run `wp --context=admin eval 'var_export( is_admin() );'`
    Then the return code should be 0
    And STDOUT should be:
      """
      true
      """

    When I run `wp --context=admin eval 'var_export( function_exists( "media_handle_upload" ) );'`
    Then the return code should be 0
    And STDOUT should be:
      """
      true
      """

    When I run `wp eval --context=admin 'add_action( "admin_init", static function () { WP_CLI::warning( "admin_init was triggered." ); } );'`
    Then the return code should be 0
    And STDERR should not contain:
      """
      admin_init was triggered.
      """

  Scenario: Frontend context can be selected (and does nothing yet...)
    Given a WP install

    When I run `wp --context=frontend eval 'var_export( is_admin() );'`
    Then the return code should be 0
    And STDOUT should be:
      """
      false
      """

    When I run `wp --context=frontend eval 'var_export( function_exists( "media_handle_upload" ) );'`
    Then the return code should be 0
    And STDOUT should be:
      """
      true
      """

    When I run `wp --context=frontend eval 'add_action( "admin_init", static function () { WP_CLI::warning( "admin_init was triggered." ); } );'`
    Then the return code should be 0
    And STDERR should not contain:
      """
      admin_init was triggered.
      """

  Scenario: Auto context can be selected and changes environment based on command
    Given a WP install
    And a context-logger.php file:
      """
      <?php
      WP_CLI::add_hook( 'before_run_command', static function () {
        $context = WP_CLI::get_runner()->context_manager->get_context();
        WP_CLI::log( "Current context: {$context}" );
      } );
      """

    When I run `wp --require=context-logger.php --context=auto post list`
    Then the return code should be 0
    And STDOUT should contain:
      """
      Current context: cli
      """

    When I run `wp --require=context-logger.php --context=auto plugin list`
    Then the return code should be 0
    And STDOUT should contain:
      """
      Current context: admin
      """

  Scenario: Unknown contexts throw an exception
    Given a WP install

    When I try `wp --context=nonsense post list`
    Then the return code should be 1
    And STDOUT should be empty
    And STDERR should contain:
      """
      Error: Unknown context 'nonsense'
      """

  Scenario: Bundled contexts can be filtered
    Given a WP install
    And a custom-contexts.php file:
      """
      <?php

      final class OverriddenAdminContext implements \WP_CLI\Context {
        public function process( $config ) {
          \WP_CLI::log( 'admin context was overridden' );
        }
      }

      final class CustomContext implements \WP_CLI\Context {
        public function process( $config ) {
          \WP_CLI::log( 'custom context was added' );
        }
      }

      WP_CLI::add_hook( 'before_registering_contexts', static function ( $contexts ) {
        unset( $contexts['frontend'] );
        $contexts['admin']          = new OverriddenAdminContext();
        $contexts['custom_context'] = new CustomContext();
        return $contexts;
      } );
      """

    When I try `wp --require=custom-contexts.php --context=frontend post list`
    Then the return code should be 1
    And STDOUT should be empty
    And STDERR should contain:
      """
      Error: Unknown context 'frontend'
      """

    When I run `wp --require=custom-contexts.php --context=admin post list`
    Then the return code should be 0
    And STDOUT should contain:
      """
      admin context was overridden
      """

    When I run `wp --require=custom-contexts.php --context=custom_context post list`
    Then the return code should be 0
    And STDOUT should contain:
      """
      custom context was added
      """

  Scenario: Core wp-admin/admin.php with CRLF lines does not fail.
    Given a WP install
    And a modify-wp-admin.php file:
    """
    <?php
    $admin_php_file = file( __DIR__ . '/wp-admin/admin.php' );
    $admin_php_file = implode( "\r\n", array_map( 'trim', $admin_php_file ) );
    file_put_contents( __DIR__ . '/wp-admin/admin.php', $admin_php_file );
    unset( $admin_php_file );
    """

    When I run `wp --require=modify-wp-admin.php --context=admin eval 'var_export( is_admin() );'`
    And STDOUT should be:
    """
    true
    """
