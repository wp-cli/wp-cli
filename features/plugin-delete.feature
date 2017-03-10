Feature: Delete WordPress plugins

  Background:
    Given a WP install

  Scenario: Delete an installed plugin
    When I run `wp plugin delete akismet`
    Then STDOUT should be:
      """
      Deleted 'akismet' plugin.
      Success: Deleted 1 of 1 plugins.
      """
    And the return code should be 0

  Scenario: Attempting to delete a plugin that doesn't exist
    When I run `wp plugin delete edit-flow`
    Then STDOUT should be:
      """
      Success: Plugin already deleted.
      """
    And STDERR should be:
      """
      Warning: The 'edit-flow' plugin could not be found.
      """
    And the return code should be 0
