Feature: Argument validation
  In order to catch errors fast
  As a user
  I need to see warnings and errors when I pass incorrect arguments

  Scenario: Passing zero arguments to a variadic command
    Given a WP installation

    When I try `wp plugin install`
    Then the return code should be 1
    Then STDOUT should contain:
      """
      usage: wp plugin install
      """

  Scenario: Validation for early commands
    Given an empty directory
    And WP files

    When I try `wp core config`
    Then the return code should be 1
    And STDERR should contain:
      """
      Parameter errors:
      """
    And STDERR should contain:
      """
      missing --dbname parameter
      """

    When I try `wp core config --invalid --other-invalid`
    Then the return code should be 1
    And STDERR should contain:
      """
      unknown --invalid parameter
      """
    And STDERR should contain:
      """
      unknown --other-invalid parameter
      """

    When I try `wp core version invalid`
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: Too many positional arguments: invalid
      """
    And STDOUT should be empty
