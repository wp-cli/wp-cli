Feature: Shutdown handler suggests workarounds for plugin/theme errors

  Scenario: Fatal error in plugin triggers shutdown handler with suggestion
    Given a WP installation
    And a wp-content/plugins/error-plugin/error-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Error Plugin
       */
      // This will cause a fatal error
      call_to_undefined_function();
      """

    When I try `wp plugin activate error-plugin`
    Then STDERR should contain:
      """
      Error: A fatal error occurred in the 'error-plugin' plugin
      """
    And STDERR should contain:
      """
      --skip-plugins=error-plugin
      """
    And the return code should be 255

  Scenario: Fatal error in plugin suggests correct plugin name
    Given a WP installation
    And a wp-content/plugins/my-problematic-plugin/plugin.php file:
      """
      <?php
      /**
       * Plugin Name: My Problematic Plugin
       */
      trigger_error('Fatal error', E_USER_ERROR);
      """

    When I try `wp plugin activate my-problematic-plugin`
    Then STDERR should contain:
      """
      Error: A fatal error occurred in the 'my-problematic-plugin' plugin
      """
    And STDERR should contain:
      """
      --skip-plugins=my-problematic-plugin
      """

  Scenario: Fatal error in mu-plugin (direct file) triggers shutdown handler
    Given a WP installation
    And a wp-content/mu-plugins/error-mu-plugin.php file:
      """
      <?php
      // This will cause a fatal error
      call_to_undefined_mu_function();
      """

    When I try `wp eval '1;'`
    Then STDERR should contain:
      """
      Error: A fatal error occurred in the 'error-mu-plugin' plugin
      """
    And STDERR should contain:
      """
      --skip-plugins=error-mu-plugin
      """
    And the return code should be 255

  Scenario: Fatal error in mu-plugin (subdirectory) triggers shutdown handler
    Given a WP installation
    And a wp-content/mu-plugins/my-mu-plugin/main.php file:
      """
      <?php
      // This will cause a fatal error
      call_to_undefined_mu_subdir_function();
      """

    When I try `wp eval '1;'`
    Then STDERR should contain:
      """
      Error: A fatal error occurred in the 'my-mu-plugin' plugin
      """
    And STDERR should contain:
      """
      --skip-plugins=my-mu-plugin
      """
    And the return code should be 255

  Scenario: Fatal error in theme triggers shutdown handler with suggestion
    Given a WP installation
    And a wp-content/themes/error-theme/style.css file:
      """
      /*
      Theme Name: Error Theme
      */
      """
    And a wp-content/themes/error-theme/index.php file:
      """
      <?php
      // Minimal theme file
      """
    And a wp-content/themes/error-theme/functions.php file:
      """
      <?php
      // This will cause a fatal error
      call_to_undefined_theme_function();
      """

    When I try `wp theme activate error-theme`
    Then STDERR should contain:
      """
      Error: A fatal error occurred in the 'error-theme' theme
      """
    And STDERR should contain:
      """
      --skip-themes=error-theme
      """

  Scenario: No suggestion for errors outside plugins/themes
    Given a WP installation

    When I try `wp eval 'call_to_undefined_function();'`
    Then STDERR should not contain:
      """
      --skip-plugins
      """
    And STDERR should not contain:
      """
      --skip-themes
      """

  Scenario: Parse error in plugin triggers shutdown handler
    Given a WP installation
    And a wp-content/plugins/syntax-error-plugin/plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Syntax Error Plugin
       */
      // Missing semicolon causes parse error
      $var = "test"
      """

    When I try `wp plugin activate syntax-error-plugin`
    Then STDERR should contain:
      """
      Error: A fatal error occurred in the 'syntax-error-plugin' plugin
      """
    And STDERR should contain:
      """
      --skip-plugins=syntax-error-plugin
      """

  Scenario: Parse error in mu-plugin triggers shutdown handler
    Given a WP installation
    And a wp-content/mu-plugins/syntax-error-mu-plugin.php file:
      """
      <?php
      // Missing semicolon causes parse error
      $var = "test"
      """

    When I try `wp eval '1;'`
    Then STDERR should contain:
      """
      Error: A fatal error occurred in the 'syntax-error-mu-plugin' plugin
      """
    And STDERR should contain:
      """
      --skip-plugins=syntax-error-mu-plugin
      """

