Feature: Manage post term

  Scenario: Postterm CRUD
    Given a WP install

    When I run `wp post term add 1 category foo`
    Then STDOUT should be:
      """
      Success: Added term.
      """

    When I run `wp post term list 1 category --fields=name,slug,taxonomy`
    Then STDOUT should be a table containing rows:
      | name | slug | taxonomy |
      | foo  | foo  | category |

    When I run `wp post term add 1 category bar`
    Then STDOUT should be:
      """
      Success: Added term.
      """

    When I run `wp post term list 1 category --fields=name,slug,taxonomy`
    Then STDOUT should be a table containing rows:
      | name | slug | taxonomy |
      | foo  | foo  | category |
      | bar  | bar  | category |

    When I run `wp post term list 1 category --format=ids`
    Then STDOUT should be:
      """
      3 2 1
      """

    When I try `wp post term list 1 foo2`
    Then STDERR should be:
      """
      Error: Invalid taxonomy foo2.
      """

    When I run `wp post term set 1 category new`
    Then STDOUT should be:
      """
      Success: Set terms.
      """

    When I run `wp post term list 1 category --fields=name,slug,taxonomy --format=count`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp post term remove 1 category new`
    Then STDOUT should be:
      """
      Success: Deleted term.
      """

    When I run `wp post term list 1 category --fields=name,slug,taxonomy --format=count`
    Then STDOUT should be:
      """
      0
      """

  Scenario: Multiple post term
    Given a WP install

    When I run `wp post term add 1 category apple`
    And I run `wp post term add 1 category apple`
    Then STDOUT should be:
      """
      Success: Added term.
      """

    When I run `wp post term set 1 category apple1 apple2`
    Then STDOUT should be:
      """
      Success: Set terms.
      """

    When I run `wp post term list 1 category --fields=name,slug,taxonomy`
    Then STDOUT should be a table containing rows:
      | name   | slug   | taxonomy |
      | apple1 | apple1 | category |
      | apple2 | apple2 | category |

  Scenario: Invalid Post ID
    Given a WP install

    When I try `wp post term add 99999 category boo`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: Could not find the post with ID 99999.
      """

  Scenario: Postterm Add invalid tax
    Given a WP install

    When I try `wp post term add 1 foo2 boo`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: Invalid taxonomy foo2.
      """
