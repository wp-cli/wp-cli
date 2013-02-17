Feature: Global flags

  Scenario: Quiet run
    Given WP install

    When I run `wp`
    Then it should run without errors
    And STDOUT should not be empty

    When I run `wp --quiet`
    Then it should run without errors
    And STDOUT should be empty
 
    When I run `wp non-existing-command --quiet`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: 'non-existing-command' is not a registered wp command. See 'wp help'.
      """

  Scenario: Debug run
    Given WP install

    When I run `wp eval 'echo CONST_WITHOUT_QUOTES;'`
    Then it should run without errors
    And STDOUT should be:
      """
      CONST_WITHOUT_QUOTES
      """

    When I run `wp --debug eval 'echo CONST_WITHOUT_QUOTES;'`
    Then the return code should be 0
    And STDOUT should contain:
      """
      Notice: Use of undefined constant CONST_WITHOUT_QUOTES
      """
