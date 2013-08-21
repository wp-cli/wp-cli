Feature: Argument validation
  In order to catch errors fast
  As a user
  I need to see warnings and errors when I pass incorrect arguments

  Scenario: Validation for early commands
    Given an empty directory
    And WP files

    When I try `wp core config invalid`
    Then the return code should be 1
    And STDERR should contain:
      """
      Parameter errors:
      """
