Feature: Manage user custom fields

  Scenario: Usermeta CRUD
    Given a WP install

    When I run `wp user-meta add 1 foo 'bar'`
    Then STDOUT should not be empty

    When I run `wp user-meta get 1 foo`
    Then STDOUT should be:
      """
      bar
      """

    When I run `wp user-meta set admin foo '[ "1", "2" ]' --format=json`
    Then STDOUT should not be empty

    When I run `wp user-meta get admin foo --format=json`
    Then STDOUT should be:
      """
      ["1","2"]
      """

    When I run `wp user-meta delete 1 foo`
    Then STDOUT should not be empty

    When I try `wp user-meta get 1 foo`
    Then the return code should be 1
