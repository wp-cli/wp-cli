Feature: Manage WordPress options

  Scenario: Option CRUD
    Given a WP install

    When I run `wp option add foo 'bar'`
    Then STDOUT should not be empty

    When I run `wp option get foo`
    Then STDOUT should be:
    """
    bar
    """

    When I run `wp option set foo '[ 1, 2 ]' --format=json`
    Then STDOUT should not be empty

    When I run `wp option get foo --format=json`
    Then STDOUT should be:
    """
    [1,2]
    """

    When I run `wp option delete foo`
    Then STDOUT should not be empty

    When I try `wp option get foo`
    Then the return code should be 1
