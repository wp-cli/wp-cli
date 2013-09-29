Feature: Manage user custom fields

  Background:
    Given a WP install

  Scenario: Usermeta CRUD

    When I run `wp user-meta add 1 foo 'bar'`
    Then STDOUT should not be empty

    When I run `wp user-meta get 1 foo`
    Then STDOUT should be:
      """
      bar
      """

    When I run `wp user-meta set 1 foo '[ "1", "2" ]' --format=json`
    Then STDOUT should not be empty

    When I run `wp user-meta get 1 foo --format=json`
    Then STDOUT should be:
      """
      ["1","2"]
      """

    When I run `wp user-meta delete 1 foo`
    Then STDOUT should not be empty

    When I try `wp user-meta get 1 foo`
    Then the return code should be 1

  Scenario: List user meta
    When I run `wp user-meta set 1 foo '[ "1", "2" ]' --format=json`
    And I run `wp user-meta list 1`
    Then STDOUT should be a table containing rows:
      | meta_key     | meta_value   |
      | nickname     | admin        |
      | foo          | ["1","2"]    |

    When I run `wp user-meta list 1 --format=csv`
    Then STDOUT should be CSV containing:
      | meta_key     | meta_value   |
      | nickname     | admin        |
      | foo          | ["1","2"]    |

    When I run `wp user-meta list 1 --format=json --fields=nickname,foo`
    Then STDOUT should be JSON containing:
      """
      {
        "nickname": "admin",
        "foo": ["1","2"]
      }
      """
