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

    When I run `wp cli info --format=json`
    Then STDOUT should contain:
      """
      "php_memory_limit":
      """

  Scenario: Warn about low memory limit
    Given an empty directory

    When I try `{INVOKE_WP_CLI_WITH_PHP_ARGS--dmemory_limit=256M} cli info`
    Then STDOUT should contain:
      """
      PHP memory limit:	256M
      """
    And STDERR should contain:
      """
      PHP memory limit is set to 256M
      """

    When I run `{INVOKE_WP_CLI_WITH_PHP_ARGS--dmemory_limit=1G} cli info`
    Then STDOUT should contain:
      """
      PHP memory limit:	1G
      """
    And STDERR should be empty

    When I run `{INVOKE_WP_CLI_WITH_PHP_ARGS--dmemory_limit=-1} cli info`
    Then STDOUT should contain:
      """
      PHP memory limit:	-1
      """
    And STDERR should be empty

    When I try `{INVOKE_WP_CLI_WITH_PHP_ARGS--dmemory_limit=512M} cli info`
    Then STDOUT should contain:
      """
      PHP memory limit:	512M
      """
    And STDERR should be empty

  @require-windows
  Scenario: wp cli info detects the MySQL binary on Windows
    Given an empty directory
    And a mysql.bat file:
      """
      @echo off
      echo mysql  Ver 8.4.0 for Win64
      """
    When I run `set "PATH=%CD%;%PATH%"&& wp cli info`
    Then STDOUT should contain:
      """
      MySQL binary:
      """
    And STDOUT should contain:
      """
      mysql.bat
      """
    And STDOUT should contain:
      """
      mysql  Ver 8.4.0 for Win64
      """
    And STDERR should be empty
    And the return code should be 0

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
