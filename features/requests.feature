Feature: Requests integration with both v1 and v2

  # This test downgrades to WordPress 5.8, but the SQLite plugin requires 6.0+
  # WP-CLI 2.7 causes deprecation warnings on PHP 8.2
  @require-mysql @less-than-php-8.2
  Scenario: Composer stack with Requests v1
    Given an empty directory
    And a composer.json file:
      """
      {
          "name": "wp-cli/composer-test",
          "type": "project",
          "require": {
              "wp-cli/wp-cli": "2.7.0",
              "wp-cli/core-command": "^2",
              "wp-cli/eval-command": "^2"
          }
      }
      """
    # Note: Composer outputs messages to stderr.
    And I run `composer install --no-interaction 2>&1`

    When I run `vendor/bin/wp cli version`
    Then STDOUT should contain:
      """
      WP-CLI 2.7.0
      """

    Given a WP installation
    And I run `vendor/bin/wp core update --version=5.8 --force`
    And I run `rm -r wp-content/themes/*`

    When I run `vendor/bin/wp core version`
    Then STDOUT should contain:
      """
      5.8
      """

    When I run `vendor/bin/wp eval "var_dump( \WP_CLI\Utils\http_request( 'GET', 'https://example.com/' ) );"`
    Then STDOUT should contain:
      """
      object(Requests_Response)
      """
    And STDOUT should contain:
      """
      HTTP/1.1 200 OK
      """
    And STDERR should be empty

  # This test downgrades to WordPress 5.8, but the SQLite plugin requires 6.0+
  @require-mysql
  Scenario: Current version with WordPress-bundled Requests v1
    Given a WP installation
    And I run `wp core update --version=5.8 --force`
    And I run `rm -r wp-content/themes/*`

    When I run `wp core version`
    Then STDOUT should contain:
      """
      5.8
      """

    When I run `wp eval "var_dump( \WP_CLI\Utils\http_request( 'GET', 'https://example.com/' ) );"`
    Then STDOUT should contain:
      """
      object(Requests_Response)
      """
    And STDOUT should contain:
      """
      HTTP/1.1 200 OK
      """
    And STDERR should be empty

    When I run `wp plugin install debug-bar`
    Then STDOUT should contain:
      """
      Success: Installed 1 of 1 plugins.
      """

  # Skip on Windows due to cURL error 60: SSL certificate problem: unable to get local issuer certificate
  # TODO: Investigate.
  @skip-windows
  Scenario: Current version with WordPress-bundled Requests v2
    Given a WP installation
    # Switch themes because twentytwentyfive requires a version newer than 6.2
    # and it would otherwise cause a fatal error further down.
    And these installed themes:
      """
      twentyten
      """
    And I try `wp theme activate twentyten`
    And I run `wp core update --version=6.2 --force`

    When I run `wp core version`
    Then STDOUT should contain:
      """
      6.2
      """

    When I run `wp eval "var_dump( \WP_CLI\Utils\http_request( 'GET', 'https://example.com/' ) );"`
    Then STDOUT should contain:
      """
      object(WpOrg\Requests\Response)
      """
    And STDOUT should contain:
      """
      HTTP/1.1 200 OK
      """
    And STDERR should be empty

    When I run `wp plugin install debug-bar`
    Then STDOUT should contain:
      """
      Success: Installed 1 of 1 plugins.
      """

  # This test downgrades to WordPress 5.8, but the SQLite plugin requires 6.0+
  @require-mysql
  Scenario: Current version with WordPress-bundled Request v1 and an alias
    Given a WP installation in 'foo'
    And I run `wp --path=foo core download --version=5.8 --force`
    And a wp-cli.yml file:
      """
      @foo:
        path: foo
      """

    When I try `WP_CLI_RUNTIME_ALIAS='{"@foo":{"path":"foo"}}' wp @foo option get home --debug`
    Then STDERR should contain:
      """
      Setting RequestsLibrary::$version to v1
      """
    And STDERR should contain:
      """
      Setting RequestsLibrary::$source to wp-core
      """

  Scenario: Deferred autoloader loads bundled Requests v2 on demand
    Given an empty directory
    And a check-deferred.php file:
      """
      <?php
      $before_autoload = class_exists( '\WpOrg\Requests\Autoload', false );
      $unrelated       = class_exists( 'WP_CLI_NonExistent_Requests_Test_Class' );
      $v1              = class_exists( '\Requests' );
      $still_unloaded  = ! class_exists( '\WpOrg\Requests\Autoload', false );
      $has_transport   = interface_exists( '\WpOrg\Requests\Transport' );
      $after_autoload  = class_exists( '\WpOrg\Requests\Autoload', false );

      echo (string) json_encode(
          array(
              'before'         => $before_autoload,
              'unrelated'      => $unrelated,
              'v1'             => $v1,
              'still_unloaded' => $still_unloaded,
              'has_transport'  => $has_transport,
              'after'          => $after_autoload,
              'version'        => WP_CLI\RequestsLibrary::get_version(),
              'source'         => WP_CLI\RequestsLibrary::get_source(),
          )
      );
      """

    When I run `wp eval-file check-deferred.php --skip-wordpress`
    Then STDOUT should be JSON containing:
      """
      {
        "before": false,
        "unrelated": false,
        "v1": false,
        "still_unloaded": true,
        "has_transport": true,
        "after": true,
        "version": "v2",
        "source": "wp-cli"
      }
      """

  Scenario: Core Requests autoloader registers v2, v1, or falls back to bundled v2
    Given an empty directory
    And a core-v2/wp-includes/Requests/src/Autoload.php file:
      """
      <?php
      namespace WpOrg\Requests;

      class Autoload {
          public static $registered = false;
          public static function register() {
              self::$registered = true;
          }
      }
      """
    And a core-v1/wp-includes/class-requests.php file:
      """
      <?php
      class Requests {
          public static $registered = false;
          public static function register_autoloader() {
              self::$registered = true;
          }
      }
      """
    And an empty core-none directory
    And a check-core-v2.php file:
      """
      <?php
      $loaded = WP_CLI\RequestsLibrary::register_wp_core_autoloader( getcwd() . '/core-v2' );
      echo (string) json_encode(
          array(
              'loaded'     => $loaded,
              'registered' => \WpOrg\Requests\Autoload::$registered,
              'version'    => WP_CLI\RequestsLibrary::get_version(),
              'source'     => WP_CLI\RequestsLibrary::get_source(),
              'class_name' => WP_CLI\RequestsLibrary::get_class_name(),
          )
      );
      """
    And a check-core-v1.php file:
      """
      <?php
      $loaded = WP_CLI\RequestsLibrary::register_wp_core_autoloader( getcwd() . '/core-v1' );
      echo (string) json_encode(
          array(
              'loaded'       => $loaded,
              'registered'   => \Requests::$registered,
              'version'      => WP_CLI\RequestsLibrary::get_version(),
              'source'       => WP_CLI\RequestsLibrary::get_source(),
              'class_name'   => WP_CLI\RequestsLibrary::get_class_name(),
              'v2_transport' => interface_exists( '\WpOrg\Requests\Transport' ),
          )
      );
      """
    And a check-core-none.php file:
      """
      <?php
      $loaded = WP_CLI\RequestsLibrary::register_wp_core_autoloader( getcwd() . '/core-none' );
      if ( ! $loaded ) {
          WP_CLI\RequestsLibrary::register_bundled_autoloader();
      }
      echo (string) json_encode(
          array(
              'loaded_core'  => $loaded,
              'version'      => WP_CLI\RequestsLibrary::get_version(),
              'source'       => WP_CLI\RequestsLibrary::get_source(),
              'has_requests' => class_exists( '\WpOrg\Requests\Requests' ),
          )
      );
      """

    When I run `wp eval-file check-core-v2.php --skip-wordpress`
    Then STDOUT should be JSON containing:
      """
      {
        "loaded": true,
        "registered": true,
        "version": "v2",
        "source": "wp-core",
        "class_name": "\\WpOrg\\Requests\\Requests"
      }
      """

    When I run `wp eval-file check-core-v1.php --skip-wordpress`
    Then STDOUT should be JSON containing:
      """
      {
        "loaded": true,
        "registered": true,
        "version": "v1",
        "source": "wp-core",
        "class_name": "\\Requests",
        "v2_transport": false
      }
      """

    When I run `wp eval-file check-core-none.php --skip-wordpress`
    Then STDOUT should be JSON containing:
      """
      {
        "loaded_core": false,
        "version": "v2",
        "source": "wp-cli",
        "has_requests": true
      }
      """

  Scenario: Syncs RequestsLibrary metadata when a v1 Requests implementation is already loaded
    Given an empty directory
    And a custom-requests-v1.php file:
      """
      <?php
      class Requests {
          public static function register_autoloader() {}
      }
      """
    And a core-v1/wp-includes/class-requests.php file:
      """
      <?php
      class Requests {
          public static function register_autoloader() {}
      }
      """
    And a check-preloaded-cli.php file:
      """
      <?php
      WP_CLI\RequestsLibrary::register_autoloader();
      echo (string) json_encode(
          array(
              'version'    => WP_CLI\RequestsLibrary::get_version(),
              'source'     => WP_CLI\RequestsLibrary::get_source(),
              'class_name' => WP_CLI\RequestsLibrary::get_class_name(),
          )
      );
      """
    And a check-preloaded-core.php file:
      """
      <?php
      $loaded = WP_CLI\RequestsLibrary::register_wp_core_autoloader( getcwd() . '/core-v1' );
      echo (string) json_encode(
          array(
              'loaded'       => $loaded,
              'version'      => WP_CLI\RequestsLibrary::get_version(),
              'source'       => WP_CLI\RequestsLibrary::get_source(),
              'class_name'   => WP_CLI\RequestsLibrary::get_class_name(),
              'v2_transport' => interface_exists( '\WpOrg\Requests\Transport' ),
          )
      );
      """

    When I run `wp --require=custom-requests-v1.php eval-file check-preloaded-cli.php --skip-wordpress`
    Then STDOUT should be JSON containing:
      """
      {
        "version": "v1",
        "source": "wp-cli",
        "class_name": "\\Requests"
      }
      """

    When I run `wp --require=core-v1/wp-includes/class-requests.php eval-file check-preloaded-core.php --skip-wordpress`
    Then STDOUT should be JSON containing:
      """
      {
        "loaded": true,
        "version": "v1",
        "source": "wp-core",
        "class_name": "\\Requests",
        "v2_transport": false
      }
      """
