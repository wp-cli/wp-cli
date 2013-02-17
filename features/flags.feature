Feature: Global flags

  Scenario: Quiet run
    Given WP install

    When I run `wp`
    Then it should run without errors
    And STDOUT should not be empty

    When I run `wp --quiet`
    Then it should run without errors
    And STDOUT should be:
      """
      """
 
    When I run `wp non-existing-command --quiet`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: 'non-existing-command' is not a registered wp command. See 'wp help'.
      """
