Feature: Get help about WP-CLI commands

  Scenario: Empty dir
    Given an empty directory
    When I run `wp help core`
    Then it should run without errors
