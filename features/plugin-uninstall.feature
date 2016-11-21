Feature: Uninstall a WordPress plugin

  Background:
    Given a WP install

  Scenario: Uninstall an installed plugin
    When I run `wp plugin uninstall akismet`
    Then STDOUT should be:
      """
      Uninstalled and deleted 'akismet' plugin.
      Success: Uninstalled 1 of 1 plugins.
      """
    And the return code should be 0

  Scenario: Attempting to uninstall a plugin that's activated
    When I run `wp plugin activate akismet`
    Then STDOUT should not be empty

    When I try `wp plugin uninstall akismet`
    Then STDERR should be:
      """
      Warning: The 'akismet' plugin is active.
      Error: No plugins uninstalled.
      """
    And the return code should be 1

  Scenario: Attempting to uninstall a plugin that doesn't exist
    When I run `wp plugin uninstall edit-flow`
    Then STDOUT should be:
      """
      Success: Plugin already uninstalled.
      """
    And STDERR should be:
      """
      Warning: The 'edit-flow' plugin could not be found.
      """
    And the return code should be 0
