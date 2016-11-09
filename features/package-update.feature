Feature: Update WP-CLI packages

  Background:
    When I run `wp package path`
    Then save STDOUT as {PACKAGE_PATH}

  Scenario: Updating WP-CLI packages runs successfully
    Given an empty directory

    When I run `wp package install danielbachhuber/wp-cli-reset-post-date-command`
    Then STDOUT should contain:
      """
      Success: Package installed successfully.
      """
    Then STDERR should be empty

    When I run `wp package update`
    Then STDOUT should contain:
      """
      Using Composer to update packages...
      """
    And STDOUT should contain:
      """
      Packages updated successfully.
      """
    And STDERR should be empty
