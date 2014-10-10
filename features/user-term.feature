Feature: Manage user term

  Scenario: userterm Add Invalid tax
    Given a WP install

    When I try `wp user term add 1 foo boo`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: Invalid taxonomy.
      """
  Scenario: userterm Update invalid tax
    Given a WP install

    When I try `wp user term update 1 foo2 boo`
    Then the return code should be 1
    And STDERR should be:
        """
        Error: Invalid taxonomy.
        """


