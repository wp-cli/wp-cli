Feature: Manage WordPress site options

  Scenario: Site Option CRUD
    Given a WP multisite install

    # String values
    When I run `wp site option add str_opt 'bar'`
    Then STDOUT should not be empty

    When I run `wp site option get str_opt`
    Then STDOUT should be:
      """
      bar
      """

    When I run `wp site option delete str_opt`
    Then STDOUT should not be empty

    When I try `wp site option get str_opt`
    Then the return code should be 1


    # Integer values
    When I run `wp site option update add_new_users 1`
    Then STDOUT should not be empty

    When I run the previous command again
    Then STDOUT should not be empty

    When I run `wp site option get add_new_users`
    Then STDOUT should be:
      """
      1
      """


    # JSON values
    When I run `wp site option set json_opt '[ 1, 2 ]' --format=json`
    Then STDOUT should not be empty

    When I run the previous command again
    Then STDOUT should not be empty

    When I run `wp site option get json_opt --format=json`
    Then STDOUT should be:
      """
      [1,2]
      """


    # Reading from files
    Given a value.json file:
      """
      {
        "foo": "bar",
        "list": [1, 2, 3]
      }
      """
    When I run `wp site option set foo --format=json < value.json`
    And I run `wp site option get foo --format=json`
    Then STDOUT should be JSON containing:
      """
      {
        "foo": "bar",
        "list": [1, 2, 3]
      }
      """
