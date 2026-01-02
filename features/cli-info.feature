Feature: Review CLI information

  Background:
    When I run `wp package path`
    Then save STDOUT as {PACKAGE_PATH}

  Scenario: Get the path to the packages directory
    Given an empty directory
    And a non-existent {PACKAGE_PATH} directory

    When I run `wp cli info --format=json`
    Then STDOUT should be JSON containing:
      """
      {"wp_cli_packages_dir_path":null}
      """

    # Allow for composer/ca-bundle using `openssl_x509_parse()` which throws PHP warnings on old versions of PHP.
    When I try `wp package install danielbachhuber/wp-cli-reset-post-date-command`
    And I run `wp cli info --format=json`
    Then STDOUT should be JSON containing:
      """
      {"wp_cli_packages_dir_path":"{PACKAGE_PATH}"}
      """

    When I run `wp cli info`
    Then STDOUT should contain:
      """
      WP-CLI packages dir:
      """

  Scenario: Display memory limit
    Given an empty directory

    When I run `wp cli info`
    Then STDOUT should contain:
      """
      PHP memory limit:
      """

  Scenario: Warn about low memory limit
    Given an empty directory

    When I try `{INVOKE_WP_CLI_WITH_PHP_ARGS--dmemory_limit=128M} cli info`
    Then STDOUT should contain:
      """
      PHP memory limit:	128M
      """
    And STDERR should contain:
      """
      PHP memory limit is set to 128M
      """

    When I run `{INVOKE_WP_CLI_WITH_PHP_ARGS--dmemory_limit=1G} cli info`
    Then STDOUT should contain:
      """
      PHP memory limit:	1G
      """
    And STDERR should be empty

  Scenario: Packages directory path should be slashed correctly
    When I run `WP_CLI_PACKAGES_DIR=/foo wp package path`
    Then STDOUT should be:
      """
      /foo/
      """

    When I run `WP_CLI_PACKAGES_DIR=/foo/ wp package path`
    Then STDOUT should be:
      """
      /foo/
      """

    When I run `WP_CLI_PACKAGES_DIR=/foo\\ wp package path`
    Then STDOUT should be:
      """
      /foo/
      """
