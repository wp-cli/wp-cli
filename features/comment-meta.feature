Feature: Manage comment custom fields

  Scenario: Comment meta CRUD
    Given a WP install

    When I run `wp comment-meta add 1 foo 'bar'`
    Then STDOUT should not be empty

    When I run `wp comment-meta get 1 foo`
    Then STDOUT should be:
      """
      bar
      """

    When I try `wp comment meta get 999999 foo`
    Then STDERR should be:
      """
      Error: Could not find the comment with ID 999999.
      """

    When I run `wp comment-meta set 1 foo '[ "1", "2" ]' --format=json`
    Then STDOUT should not be empty

    When I run `wp comment-meta get 1 foo --format=json`
    Then STDOUT should be:
      """
      ["1","2"]
      """

    When I run `wp comment-meta delete 1 foo`
    Then STDOUT should not be empty

    When I try `wp comment-meta get 1 foo`
    Then the return code should be 1

  Scenario: Add comment meta with JSON serialization
    Given a WP install

    When I try `wp comment meta add 1 foo '-- hi'`
    Then STDERR should be:
      """
      Error: Parameter errors:
       unknown -- hi parameter
      """

    When I run `wp comment meta add 1 foo '"-- hi"' --format=json`
    Then STDOUT should contain:
      """
      Success:
      """

    When I run `wp comment meta get 1 foo`
    Then STDOUT should be:
      """
      -- hi
      """
