Feature: Manage WordPress plugins

  Scenario: Checking the plugin status
    Given a WP install

    When I run `wp plugin status`
    Then it should run without errors
    And STDOUT should not be empty

    When I run `wp plugin status hello`
    Then it should run without errors
    And STDOUT should contain:
      """
      Plugin hello details:
          Name: Hello Dolly
      """

    When I run `wp plugin status non-existent-plugin`
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: The plugin 'non-existent-plugin' could not be found.
      """
