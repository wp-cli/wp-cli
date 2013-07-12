Feature: Export content.

  Scenario: Basic export
    Given a WP install

    When I try `wp export`
    Then the return code should be 0
    And STDOUT should contain:
      """
      All done with export
      """
