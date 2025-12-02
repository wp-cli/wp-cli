Feature: Skipping plugins

  Scenario: Skipping plugins via global flag
    Given a WP installation
    And I run `wp plugin install https://github.com/wp-cli/sample-plugin/archive/refs/heads/master.zip`
    And I run `wp plugin activate akismet sample-plugin`

    When I run `wp eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "sample_plugin" ) );'`
    Then STDOUT should be:
      """
      truetrue
      """

    # The specified plugin should be skipped
    When I run `wp --skip-plugins=akismet eval 'var_export( defined("AKISMET_VERSION") );'`
    Then STDOUT should be:
      """
      false
      """

    # The specified plugin should still show up as an active plugin
    When I run `wp --skip-plugins=akismet plugin status akismet`
    Then STDOUT should contain:
      """
      Status: Active
      """

    # The un-specified plugin should continue to be loaded
    When I run `wp --skip-plugins=akismet eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "sample_plugin" ) );'`
    Then STDOUT should be:
      """
      falsetrue
      """

    # Can specify multiple plugins to skip
    When I try `wp eval --skip-plugins=sample-plugin,akismet 'echo sample_plugin();'`
    Then STDERR should contain:
      """
      Call to undefined function sample_plugin()
      """

    # No plugins should be loaded when --skip-plugins doesn't have a value
    When I run `wp --skip-plugins eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "sample_plugin" ) );'`
    Then STDOUT should be:
      """
      falsefalse
      """

  Scenario: Skipping multiple plugins via config file
    Given a WP installation
    And I run `wp plugin install https://github.com/wp-cli/sample-plugin/archive/refs/heads/master.zip`
    And a wp-cli.yml file:
      """
      skip-plugins:
        - sample-plugin
        - akismet
      """

    When I run `wp plugin activate sample-plugin`
    And I try `wp eval 'echo sample_plugin();'`
    Then STDERR should contain:
      """
      Call to undefined function sample_plugin()
      """

  Scenario: Skipping all plugins via config file
    Given a WP installation
    And I run `wp plugin install https://github.com/wp-cli/sample-plugin/archive/refs/heads/master.zip`
    And a wp-cli.yml file:
      """
      skip-plugins: true
      """

    When I run `wp plugin activate sample-plugin`
    And I try `wp eval 'echo sample_plugin();'`
    Then STDERR should contain:
      """
      Call to undefined function sample_plugin()
      """

  Scenario: Skip network active plugins
    Given a WP multisite installation
    And I run `wp plugin install https://github.com/wp-cli/sample-plugin/archive/refs/heads/master.zip`

    When I try `wp plugin deactivate akismet sample-plugin`
    Then STDERR should be:
      """
      Warning: Plugin 'akismet' isn't active.
      Warning: Plugin 'sample-plugin' isn't active.
      """
    And STDOUT should be:
      """
      Success: Plugins already deactivated.
      """
    And the return code should be 0

    When I run `wp plugin activate --network akismet sample-plugin`
    And I run `wp eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "sample_plugin" ) );'`
    Then STDOUT should be:
      """
      truetrue
      """

    When I run `wp --skip-plugins eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "sample_plugin" ) );'`
    Then STDOUT should be:
      """
      falsefalse
      """

    When I run `wp --skip-plugins=akismet eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "sample_plugin" ) );'`
    Then STDOUT should be:
      """
      falsetrue
      """

    When I run `wp --skip-plugins=sample-plugin eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "sample_plugin" ) );'`
    Then STDOUT should be:
      """
      truefalse
      """
