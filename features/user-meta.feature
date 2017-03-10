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

    When I try `wp user-meta get 2 foo`
    Then STDERR should be:
      """
      Error: Invalid user ID, email or login: '2'
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

    When I run `wp user meta add 1 foo bar`
    And I run `wp user meta add 1 foo bar2`
    And I run `wp user meta add 1 foo bar3`
    Then STDOUT should not be empty

    When I run `wp user meta delete 1 foo bar2`
    And I run `wp user meta list 1 --keys=foo --format=count`
    Then STDOUT should be:
      """
      2
      """

    When I run `wp user meta delete 1 foo`
    And I run `wp user meta list 1 --keys=foo --format=count`
    Then STDOUT should be:
      """
      0
      """

  Scenario: List user meta
    Given a WP install

    When I run `wp user meta set 1 foo '[ "1", "2" ]' --format=json`
    Then STDOUT should not be empty

    When I run `wp user meta list 1 --format=json --keys=nickname,foo --fields=meta_key,meta_value`
    Then STDOUT should be JSON containing:
      """
      [{"meta_key":"nickname","meta_value":"admin"},{"meta_key":"foo","meta_value":["1","2"]}]
      """

    When I run `wp user meta list 1 --keys=nickname,foo`
    Then STDOUT should be a table containing rows:
      | user_id | meta_key | meta_value     |
      | 1       | nickname | admin          |
      | 1       | foo      | ["1","2"]      |

    When I run `wp user meta list admin --keys=nickname,foo`
    Then STDOUT should be a table containing rows:
      | user_id | meta_key | meta_value     |
      | 1       | nickname | admin          |
      | 1       | foo      | ["1","2"]      |
