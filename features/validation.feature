Feature: Argument validation
  In order to catch errors fast
  As a user
  I need to see warnings and errors when I pass incorrect arguments

  Scenario: Passing zero arguments to a variadic command
    Given a WP install

    When I try `wp plugin install`
    Then the return code should be 1
    Then STDOUT should contain:
      """
      usage: wp plugin install
      """

  Scenario: Validation for early commands
    Given an empty directory
    And WP files

    When I try `wp core config invalid`
    Then the return code should be 1
    And STDERR should contain:
      """
      Parameter errors:
      """

    When I try `wp core config invalid --invalid --other-invalid`
    Then the return code should be 1
    And STDERR should contain:
      """
      unknown --invalid parameter
      """
    And STDERR should contain:
      """
      unknown --other-invalid parameter
      """
