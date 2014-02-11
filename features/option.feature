Feature: Manage WordPress options

  Scenario: Option CRUD
    Given a WP install

    # String values
    When I run `wp option add str_opt 'bar'`
    Then STDOUT should not be empty

    When I run `wp option get str_opt`
    Then STDOUT should be:
      """
      bar
      """

    When I run `wp option delete str_opt`
    Then STDOUT should not be empty

    When I try `wp option get str_opt`
    Then the return code should be 1


    # Integer values
    When I run `wp option update blog_public 0`
    Then STDOUT should not be empty

    When I run the previous command again
    Then STDOUT should not be empty

    When I run `wp option get blog_public`
    Then STDOUT should be:
      """
      0
      """


    # JSON values
    When I run `wp option set json_opt '[ 1, 2 ]' --format=json`
    Then STDOUT should not be empty

    When I run the previous command again
    Then STDOUT should not be empty

    When I run `wp option get json_opt --format=json`
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
    When I run `wp option set foo --format=json < value.json`
    And I run `wp option get foo --format=json`
    Then STDOUT should be JSON containing:
      """
      {
        "foo": "bar",
        "list": [1, 2, 3]
      }
      """
