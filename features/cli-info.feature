Feature: Review CLI information

  Scenario: Get the path to the packages directory
    Given an empty directory

    When I run `wp cli info --format=json`
    Then STDOUT should be JSON containing:
      """
      {"wp_cli_packages_dir_path":"/tmp/wp-cli-home/.wp-cli/packages/"}
      """

    When I run `WP_CLI_PACKAGES_DIR=/tmp/packages wp cli info --format=json`
    Then STDOUT should be JSON containing:
      """
      {"wp_cli_packages_dir_path":"/tmp/packages/"}
      """
