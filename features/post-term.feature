Feature: Manage post term

  Scenario: Postterm CRUD
    Given a WP install

    When I run `wp post term add 1 foo category`
    Then STDOUT should be:
      """
      Success: Added term.
      """

    When I run `wp post term list 1 --taxonomies=category --fields=name,slug,taxonomy`
    Then STDOUT should be a table containing rows:
      | name | slug | taxonomy |
      | foo  | foo  | category |

    When I run `wp post term add 1 bar category`
    Then STDOUT should be:
      """
      Success: Added term.
      """

    When I run `wp post term list 1 --taxonomies=category --fields=name,slug,taxonomy`
    Then STDOUT should be a table containing rows:
      | name | slug | taxonomy |
      | foo  | foo  | category |
      | bar  | bar  | category |

    When I run `wp post term set 1 new category`
    Then STDOUT should be:
      """
      Success: Set terms.
      """

    When I run `wp post term list 1 --taxonomies=category --fields=name,slug,taxonomy`
    Then STDOUT should be a table containing rows:
      | name | slug | taxonomy |
      | new  | new  | category |

    When I run `wp post term remove 1 new category`
    Then STDOUT should be:
      """
      Success: Deleted term.
      """

  Scenario: Multiple post term
    Given a WP install

    When I run `wp post term add 1 apple category`
    And I run `wp post term add 1 apple category`
    Then STDOUT should be:
      """
      Success: Added term.
      """

    When I run `wp post term set 1 'apple1, apple2' category`
    Then STDOUT should be:
      """
      Success: Set terms.
      """

    When I run `wp post term list 1 --taxonomies=category --fields=name,slug,taxonomy`
    Then STDOUT should be a table containing rows:
      | name   | slug   | taxonomy |
      | apple1 | apple1 | category |
      | apple2 | apple2 | category |

  Scenario: Postterm Add invalid tax
    Given a WP install

    When I try `wp post term add 1 foo2 boo`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: Invalid taxonomy.
	  """