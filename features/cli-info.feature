Feature: Review CLI information

  Background:
    When I run `wp package path`
    Then save STDOUT as {PACKAGE_PATH}

  Scenario: Get the path to the packages directory
    Given an empty directory
	And an empty {PACKAGE_PATH} directory

    When I run `wp cli info --format=json`
    Then STDOUT should be JSON containing:
      """
      {"wp_cli_packages_dir_path":null}
      """

    When I run `wp package install danielbachhuber/wp-cli-reset-post-date-command`
    Then STDERR should be empty

    When I run `wp cli info --format=json`
    Then STDOUT should be JSON containing:
      """
      {"wp_cli_packages_dir_path":"/tmp/wp-cli-home/.wp-cli/packages/"}
      """

    When I run `wp cli info`
    Then STDOUT should contain:
      """
      WP-CLI packages dir:
      """
