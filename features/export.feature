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

  Scenario: Export argument validator
    Given a WP install

    When I try `wp export --post_type=wp-cli-party`
    Then STDERR should contain:
      """
      Warning: The post type wp-cli-party does not exist.
      """

    When I try `wp export --author=invalid-author`
    Then STDERR should contain:
      """
      Warning: Could not find a matching author for invalid-author
      """

    When I try `wp export --start_date=invalid-date`
    Then STDERR should contain:
      """
      Warning: The start_date invalid-date is invalid
      """

    When I try `wp export --end_date=invalid-date`
    Then STDERR should contain:
      """
      Warning: The end_date invalid-date is invalid
      """
