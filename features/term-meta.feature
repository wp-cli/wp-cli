Feature: Manage term custom fields

  @require-wp-4.4
  Scenario: Term meta CRUD
    Given a WP install

    When I run `wp term meta add 1 foo 'bar'`
    Then STDOUT should not be empty

    When I run `wp term meta get 1 foo`
    Then STDOUT should be:
      """
      bar
      """

    When I try `wp term meta get 999999 foo`
    Then STDERR should be:
      """
      Error: Could not find the term with ID 999999.
      """

    When I run `wp term meta set 1 foo '[ "1", "2" ]' --format=json`
    Then STDOUT should not be empty

    When I run `wp term meta get 1 foo --format=json`
    Then STDOUT should be:
      """
      ["1","2"]
      """

    When I run `wp term meta delete 1 foo`
    Then STDOUT should not be empty

    When I try `wp term meta get 1 foo`
    Then the return code should be 1
