Feature: Manage maintenance mode of WordPress install.

  Background:
    Given a WP install

  Scenario: Manage maintenance mode.

    When I run `wp maintenance status`
    Then STDOUT should be:
      """
      Success: Maintenance mode is off.
      """

    When I run `wp maintenance on`
    Then STDOUT should contain:
      """
      Success: Enabled Maintenance mode.
      """

    When I run `wp maintenance status`
    Then STDOUT should be:
      """
      Success: Maintenance mode is on.
      """

    When I try `wp maintenance on`
    Then STDERR should be:
      """
      Error: Maintenance mode already enabled.
      """

    When I run `wp maintenance on --force`
    Then STDOUT should contain:
      """
      Success: Enabled Maintenance mode.
      """

    When I run `wp maintenance off`
    Then STDOUT should contain:
      """
      Success: Disabled Maintenance mode.
      """

    When I try `wp maintenance off`
    Then STDERR should be:
      """
      Error: Maintenance mode already disabled.
      """
