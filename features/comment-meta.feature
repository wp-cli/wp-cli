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
