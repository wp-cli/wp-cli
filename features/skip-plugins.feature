Feature: Skipping plugins

  Scenario: Skipping plugins via global flag
    Given a WP install
    And I run `wp plugin activate hello akismet`

    When I run `wp eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "hello_dolly" ) );'`
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
    When I run `wp --skip-plugins=akismet eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "hello_dolly" ) );'`
    Then STDOUT should be:
      """
      falsetrue
      """

    # Can specify multiple plugins to skip
    When I try `wp eval --skip-plugins=hello,akismet 'echo hello_dolly();'`
    Then STDERR should contain:
      """
      Call to undefined function hello_dolly()
      """

    # No plugins should be loaded when --skip-plugins doesn't have a value
    When I run `wp --skip-plugins eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "hello_dolly" ) );'`
    Then STDOUT should be:
      """
      falsefalse
      """

  Scenario: Skipping multiple plugins via config file
    Given a WP install
    And a wp-cli.yml file:
      """
      skip-plugins:
        - hello
        - akismet
      """

    When I run `wp plugin activate hello`
    And I try `wp eval 'echo hello_dolly();'`
    Then STDERR should contain:
      """
      Call to undefined function hello_dolly()
      """

  Scenario: Skipping all plugins via config file
    Given a WP install
    And a wp-cli.yml file:
      """
      skip-plugins: true
      """

    When I run `wp plugin activate hello`
    And I try `wp eval 'echo hello_dolly();'`
    Then STDERR should contain:
      """
      Call to undefined function hello_dolly()
      """

  Scenario: Skip network active plugins
    Given a WP multisite install
    And I run `wp plugin deactivate akismet hello`
    And I run `wp plugin activate --network akismet hello`

    When I run `wp eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "hello_dolly" ) );'`
    Then STDOUT should be:
      """
      truetrue
      """

    When I run `wp --skip-plugins eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "hello_dolly" ) );'`
    Then STDOUT should be:
      """
      falsefalse
      """

    When I run `wp --skip-plugins=akismet eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "hello_dolly" ) );'`
    Then STDOUT should be:
      """
      falsetrue
      """

    When I run `wp --skip-plugins=hello eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "hello_dolly" ) );'`
    Then STDOUT should be:
      """
      truefalse
      """
