@require-wp-5.2
Feature: Shutdown handler suggests workarounds for plugin/theme errors

  Background:
    Given a WP installation
    And a session_no file:
      """
      n
      """
    And a session_yes file:
      """
      y
      """

  Scenario: Fatal error in plugin triggers shutdown handler with suggestion
    Given a wp-content/plugins/error-plugin/error-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Error Plugin
       */
      // Working plugin initially
      """

    When I run `wp plugin activate error-plugin`
    Then STDOUT should contain:
      """
      Success:
      """

    Given a wp-content/plugins/error-plugin/error-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Error Plugin
       */
      // This will cause a fatal error
      call_to_undefined_function();
      """

    When I try `wp plugin list < session_yes`
    Then STDERR should contain:
      """
      critical error
      """
    And STDERR should contain:
      """
      --skip-plugins=error-plugin
      """
    And the return code should be 255

  Scenario: Fatal error in plugin suggests correct plugin name
    Given a wp-content/plugins/my-problematic-plugin/plugin.php file:
      """
      <?php
      /**
       * Plugin Name: My Problematic Plugin
       */
      // Working plugin initially
      """

    When I run `wp plugin activate my-problematic-plugin`
    Then STDOUT should contain:
      """
      Success:
      """

    Given a wp-content/plugins/my-problematic-plugin/plugin.php file:
      """
      <?php
      /**
       * Plugin Name: My Problematic Plugin
       */
      trigger_error('Fatal error', E_USER_ERROR);
      """

    When I try `wp plugin list < session_yes`
    Then STDERR should contain:
      """
      critical error
      """
    And STDERR should contain:
      """
      --skip-plugins=my-problematic-plugin
      """

  Scenario: Fatal error in mu-plugin triggers shutdown handler
    Given a wp-content/mu-plugins/error-mu-plugin.php file:
      """
      <?php
      // This will cause a fatal error
      call_to_undefined_mu_function();
      """

    When I try `wp plugin list < session_yes`
    Then STDERR should contain:
      """
      critical error
      """
    And STDERR should contain:
      """
      --skip-plugins=error-mu-plugin
      """
    And the return code should be 255

  Scenario: Fatal error in theme triggers shutdown handler with suggestion
    Given a wp-content/themes/error-theme/style.css file:
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
      // Working theme initially
      """

    When I run `wp theme activate error-theme`
    Then STDOUT should contain:
      """
      Success:
      """

    Given a wp-content/themes/error-theme/functions.php file:
      """
      <?php
      // This will cause a fatal error
      call_to_undefined_theme_function();
      """

    When I try `wp theme list < session_yes`
    Then STDERR should contain:
      """
      critical error
      """
    And STDERR should contain:
      """
      --skip-themes=error-theme
      """

  Scenario: No suggestion for errors outside plugins/themes
    When I try `wp eval 'call_to_undefined_function();' < session_yes`
    Then STDERR should contain:
      """
      This error may have been caused by a theme or plugin
      """

  Scenario: Parse error in plugin triggers shutdown handler
    Given a wp-content/plugins/syntax-error-plugin/plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Syntax Error Plugin
       */
      // Working plugin initially
      """

    When I run `wp plugin activate syntax-error-plugin`
    Then STDOUT should contain:
      """
      Success:
      """

    Given a wp-content/plugins/syntax-error-plugin/plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Syntax Error Plugin
       */
      // Missing semicolon causes parse error
      $var = "test"
      """

    When I try `wp plugin list < session_yes`
    Then STDERR should contain:
      """
      critical error
      """
    And STDERR should contain:
      """
      --skip-plugins=syntax-error-plugin
      """

  Scenario: Parse error in mu-plugin triggers shutdown handler
    Given a wp-content/mu-plugins/syntax-error-mu-plugin.php file:
      """
      <?php
      // Missing semicolon causes parse error
      $var = "test"
      """

    When I try `wp plugin list < session_yes`
    Then STDERR should contain:
      """
      critical error
      """
    And STDERR should contain:
      """
      --skip-plugins=syntax-error-mu-plugin
      """

  Scenario: Automatic rerun with WP_CLI_SKIP_PROMPT=no disables prompting
    Given a wp-content/plugins/broken-plugin/broken-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Broken Plugin
       */
      // Working initially
      """

    When I run `wp plugin activate broken-plugin`
    Then STDOUT should contain:
      """
      Success:
      """

    Given a wp-content/plugins/broken-plugin/broken-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Broken Plugin
       */
      call_to_undefined();
      """

    When I try `WP_CLI_SKIP_PROMPT=no wp plugin list`
    Then STDERR should contain:
      """
      --skip-plugins=broken-plugin
      """
    And STDERR should not contain:
      """
      Would you like to run the command again
      """

