Feature: Toggle the activation status of a plugin

  Background:
    Given a WP install

  Scenario: Toggle the status of a plugin
    When I run `wp plugin toggle akismet`
    Then STDOUT should be:
      """
      Plugin 'akismet' activated.
      Success: Toggled 1 of 1 plugins.
      """

    When I run `wp plugin toggle akismet`
    Then STDOUT should be:
      """
      Plugin 'akismet' deactivated.
      Success: Toggled 1 of 1 plugins.
      """

  Scenario: Toggling the status of a plugin that doesn't exist
    When I try `wp plugin toggle akismet edit-flow`
    Then STDERR should be:
      """
      Warning: The 'edit-flow' plugin could not be found.
      Error: Only toggled 1 of 2 plugins.
      """
    And STDOUT should be:
      """
      Plugin 'akismet' activated.
      """
    And the return code should be 1

    When I try `wp plugin toggle edit-flow co-authors-plus`
    Then STDERR should be:
      """
      Warning: The 'edit-flow' plugin could not be found.
      Warning: The 'co-authors-plus' plugin could not be found.
      Error: No plugins toggled.
      """
    And STDOUT should be empty
    And the return code should be 1
