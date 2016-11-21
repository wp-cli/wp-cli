Feature: Activate WordPress plugins

  Background:
    Given a WP install

  Scenario: Activate a plugin that's already installed
    When I run `wp plugin activate akismet`
    Then STDOUT should be:
      """
      Plugin 'akismet' activated.
      Success: Activated 1 of 1 plugins.
      """
    And the return code should be 0

  Scenario: Attempt to activate a plugin that's not installed
    When I try `wp plugin activate edit-flow`
    Then STDERR should be:
      """
      Warning: The 'edit-flow' plugin could not be found.
      Error: No plugins activated.
      """
    And the return code should be 1

    When I try `wp plugin activate akismet hello edit-flow`
    Then STDERR should be:
      """
      Warning: The 'edit-flow' plugin could not be found.
      Error: Only activated 2 of 3 plugins.
      """
    And STDOUT should be:
      """
      Plugin 'akismet' activated.
      Plugin 'hello' activated.
      """
    And the return code should be 1
