Feature: Export content.

  Scenario: Basic export
    Given a WP install

    When I run `wp export`
    Then it should run without errors
    And STDOUT should not be empty
