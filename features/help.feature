Feature: Get help about WP-CLI commands

  Scenario: Empty dir
    Given an empty directory

    When I run `wp help`
    Then it should run without errors
    And STDOUT should contain:
      """
      Available commands:
      """

    When I run `wp help core`
    Then it should run without errors
    And STDOUT should contain:
      """
      usage: wp core
      """

    When I run `wp help core download`
    Then it should run without errors
    And STDOUT should contain:
      """
      WP-CORE-DOWNLOAD(1)
      """

    When I run `wp help non-existent-command`
    Then the return code should be 1

  Scenario: Getting help for a third-party command
    Given a WP install
    And a google-sitemap-generator-cli plugin zip
    And I run `wp plugin install --activate {PLUGIN_ZIP}`
    And it should run without errors
    And I run `wp plugin install --activate google-sitemap-generator`
    And it should run without errors

    When I run `wp help google-sitemap`
    Then it should run without errors
    And STDOUT should contain:
      """
      usage: wp google-sitemap
      """
