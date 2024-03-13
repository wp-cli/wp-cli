Feature: Including plugins

  Scenario: Including plugins via global flag
    Given a WP installation
    And I run `wp plugin activate hello akismet`

    When I run `wp eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "hello_dolly" ) );'`
    Then STDOUT should be:
      """
      truetrue
      """

    # The specified plugin should not be skipped
    When I run `wp --include-plugins=akismet eval 'var_export( defined("AKISMET_VERSION") );'`
    Then STDOUT should be:
      """
      true
      """

    # The un-specified plugin should not be loaded
    When I run `wp --include-plugins=akismet eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "hello_dolly" ) );'`
    Then STDOUT should be:
      """
      truefalse
      """

    # No plugins should be loaded when --include-plugins doesn't have a value
    When I run `wp --skip-plugins eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "hello_dolly" ) );'`
    Then STDOUT should be:
      """
      falsefalse
      """

  Scenario: Including multiple plugins via config file
    Given a WP installation
    And a wp-cli.yml file:
      """
      include-plugins:
        - hello
        - akismet
      """

    When I run `wp plugin activate hello`
    And I try `wp eval 'var_export( function_exists( "hello_dolly" ) );'`
    Then STDOUT should be:
      """
      true
      """

  Scenario: Include network active plugins
    Given a WP multisite installation

    When I try `wp plugin deactivate akismet hello`
    Then STDERR should be:
      """
      Warning: Plugin 'akismet' isn't active.
      Warning: Plugin 'hello' isn't active.
      """
    And STDOUT should be:
      """
      Success: Plugins already deactivated.
      """
    And the return code should be 0

    When I run `wp plugin activate --network akismet hello`
    And I run `wp eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "hello_dolly" ) );'`
    Then STDOUT should be:
      """
      truetrue
      """

    When I run `wp --include-plugins eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "hello_dolly" ) );'`
    Then STDOUT should be:
      """
      falsefalse
      """

    When I run `wp --include-plugins=akismet eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "hello_dolly" ) );'`
    Then STDOUT should be:
      """
      truefalse
      """

    When I run `wp --include-plugins=hello eval 'var_export( defined("AKISMET_VERSION") );var_export( function_exists( "hello_dolly" ) );'`
    Then STDOUT should be:
      """
      falsetrue
      """
