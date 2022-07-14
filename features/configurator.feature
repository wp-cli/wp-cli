Feature: Define global parameters

  Scenario: Throws exception for an invalid config spec
    Given an empty directory
    And a wp-invalid-config-spec.php file:
      """
      <?php
      // A hacky way to inject our constant into the runtime.
      define( 'WP_CLI_CONFIG_SPEC_PATH', 'invalid-path' );
      
      $wp_cli_info = json_decode( trim( shell_exec( 'wp cli info --format=json' ) ), true );
      require $wp_cli_info['wp_cli_dir_path'] . '/php/boot-fs.php';
      """

    When I try `php wp-invalid-config-spec.php option get home`
    Then STDOUT should contain:
      """
      Uncaught Exception: Unable to load config spec:
      """

  Scenario: Errors when a global parameter doesn't exist
    Given a WP installation in 'foo'
    And a empty-config-spec.php file:
      """
      <?php
      return [];
      """
    And a wp-empty-config-spec.php file:
      """
      <?php
      // A hacky way to inject our constant into the runtime.
      define( 'WP_CLI_CONFIG_SPEC_PATH', __DIR__ . '/empty-config-spec.php' );
      
      $wp_cli_info = json_decode( trim( shell_exec( 'wp cli info --format=json' ) ), true );
      require $wp_cli_info['wp_cli_dir_path'] . '/php/boot-fs.php';
      """

    When I run `wp --path=foo option get home`
    Then STDOUT should be:
      """
      https://example.com
      """

    When I try `php wp-empty-config-spec.php --path=foo option get home`
    Then STDERR should be:
      """
      Error: Parameter errors:
        unknown --path parameter
      """
