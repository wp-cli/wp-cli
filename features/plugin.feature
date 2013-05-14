Feature: Manage WordPress plugins

  Scenario: Checking the plugin status
    Given a WP install

    When I run `wp plugin status`
    Then STDOUT should not be empty

    When I run `wp plugin status hello`
    Then STDOUT should contain:
      """
      Plugin hello details:
          Name: Hello Dolly
      """

    When I try `wp plugin status non-existent-plugin`
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: The plugin 'non-existent-plugin' could not be found.
      """

    When I run `wp plugin list --format=json`
    And STDOUT should not be empty
