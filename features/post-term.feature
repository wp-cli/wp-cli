Feature: Manage post term

  Scenario: Postterm Add Valid Category
    Given a WP install

    When I run `wp post term add 1 foo category`
    Then STDOUT should be:
    """
      Success: Added term.
      """

  Scenario: Postterm Add Invalid tax
    Given a WP install

    When I try `wp post term add 1 foo boo`
    Then the return code should be 1
    And STDERR should be:
    """
      Error: Invalid taxonomy.
      """

  Scenario: Postterm Update valid category
    Given a WP install

    When I run `wp post term update 1 foo2 category`
    Then STDOUT should be:
    """
      Success: Updated term.
      """
  Scenario: Postterm Update invalid tax
    Given a WP install

    When I try `wp post term update 1 foo2 boo`
    Then the return code should be 1
    And STDERR should be:
    """
	      Error: Invalid taxonomy.
	      """

  Scenario: List post meta
    Given a WP install

    When I run `wp post term add 1 apple category`
    And I run `wp post term add 1 apple category`
    Then STDOUT should be:
    """
      Success: Added term.
      """

    When I run `wp post term update 1 'apple1, apple2' category`
    Then STDOUT should be:
    """
      Success: Updated term.
      """

    When I run `wp post term list 1 --taxonomies=category --fields=name,slug,taxonomy`
    Then STDOUT should be a table containing rows:
      | name   | slug   | taxonomy |
      | apple1 | apple1 | category |
      | apple2 | apple2 | category |

