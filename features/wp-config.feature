Feature: wp-config

  Scenario: Default WordPress install with WP_CONFIG_PATH specified in environment variable
    Given a WP installation
    And a wp-config-override.php file:
      """
      <?php
      define( 'DB_NAME', 'wp_cli_test' );
      define( 'DB_USER', '{DB_USER}' );
      define( 'DB_PASSWORD', '{DB_PASSWORD}' );
      define( 'DB_HOST', '{DB_HOST}' );
      define( 'DB_CHARSET', 'utf8' );
      define( 'DB_COLLATE', '' );
      $table_prefix = 'wp_';

      // Provide custom define in override only that we can test against
      define('TEST_CONFIG_OVERRIDE', 'success');

      if ( ! defined( 'ABSPATH' ) )
        define( 'ABSPATH', dirname( __FILE__ ) . '/' );
      require_once( ABSPATH . 'wp-settings.php' );
      """

    When I try `wp eval "echo 'TEST_CONFIG_OVERRIDE => ' . TEST_CONFIG_OVERRIDE;"`
    And STDERR should contain:
      """
      TEST_CONFIG_OVERRIDE
      """

    When I run `WP_CONFIG_PATH=wp-config-override.php wp eval "echo 'TEST_CONFIG_OVERRIDE => ' . TEST_CONFIG_OVERRIDE;"`
    Then STDERR should be empty
    And STDOUT should contain:
      """
      TEST_CONFIG_OVERRIDE => success
      """

  # Regression test for https://github.com/wp-cli/extension-command/issues/247
  Scenario: __FILE__ and __DIR__ in wp-config.php don't point into the PHAR filesystem
    Given a WP installation
    And a new Phar with the same version
    And a wp-config.php file:
      """
      <?php
      define( 'DB_NAME', 'wp_cli_test' );
      define( 'DB_USER', '{DB_USER}' );
      define( 'DB_PASSWORD', '{DB_PASSWORD}' );
      define( 'DB_HOST', '{DB_HOST}' );
      define( 'DB_CHARSET', 'utf8' );
      define( 'DB_COLLATE', '' );
      $table_prefix = 'wp_';

      // Provide defines that make use of __FILE__ and __DIR__.
      define( 'WP_CONTENT_DIR', __FILE__ . '/my-content/' );
      define( 'WP_PLUGIN_DIR', __DIR__ . '/my-plugins/' );

      if ( ! defined( 'ABSPATH' ) )
        define( 'ABSPATH', dirname( __FILE__ ) . '/' );
      require_once( ABSPATH . 'wp-settings.php' );
      """

    When I run `{PHAR_PATH} eval "echo 'WP_CONTENT_DIR => ' . WP_CONTENT_DIR;"`
    Then STDOUT should not contain:
      """
      WP_CONTENT_DIR => phar://
      """

    When I run `{PHAR_PATH} eval "echo 'WP_PLUGIN_DIR => ' . WP_PLUGIN_DIR;"`
    Then STDOUT should not contain:
      """
      WP_PLUGIN_DIR => phar://
      """
