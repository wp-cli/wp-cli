Feature: Export content.

  Scenario: Basic export
    Given a WP install

    When I run `wp export`
    Then STDOUT should contain:
      """
      All done with export
      """

  Scenario: Term with a non-existent parent
    Given a WP install

    When I run `wp term create category Apple --parent=99 --porcelain`
    Then STDOUT should be a number

    When I try `wp export`
    Then STDERR should be:
      """
      Error: Term is missing a parent.
      """
