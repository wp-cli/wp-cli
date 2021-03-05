Feature: wp-config

  Scenario: Default WordPress install with WP_CONFIG_PATH specified in environment variable
    Given a WP installation
    And a wp-config-override.php file:
      """
      <?php
      define('DB_NAME', 'wp_cli_test');
      define('DB_USER', '{DB_USER}');
      define('DB_PASSWORD', '{DB_PASSWORD}');
      define('DB_HOST', '{DB_HOST}');
      define('DB_CHARSET', 'utf8');
      define('DB_COLLATE', '');
      $table_prefix = 'wp_';

      // Provide custom define in override only that we can test against
      define('TEST_CONFIG_OVERRIDE', 'success');

      if ( !defined('ABSPATH') )
        define('ABSPATH', dirname(__FILE__) . '/');
      require_once(ABSPATH . 'wp-settings.php');
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
