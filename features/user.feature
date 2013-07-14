Feature: Manage WordPress users

  Scenario: User CRUD operations
    Given a WP install

    When I run `wp user create testuser testuser@example.com --porcelain`
    Then STDOUT should match '%d'
    And save STDOUT as {USER_ID}

    When I try the previous command again
    Then the return code should be 1

    When I run `wp user update {USER_ID} --display_name=Foo`
    And I run `wp user get {USER_ID}`
    Then STDOUT should be a table containing rows:
      | Field        | Value     |
      | ID           | {USER_ID} |
      | display_name | Foo       |

    When I run `wp user delete {USER_ID}`
    Then STDOUT should not be empty

  Scenario: Generating and deleting users
    Given a WP install

    When I run `wp user generate --count=9`
    And I run `wp user list --format=count`
    Then STDOUT should be:
      """
      10
      """

    When I try `wp user delete invalid-user $(wp user list --format=ids)`
    And I run `wp user list --format=count`
    Then STDOUT should be:
      """
      0
      """

  Scenario: Importing users from a CSV file
    Given a WP install
    And a users.csv file:
      """
      user_login,user_email,display_name,role
      bobjones,bobjones@domain.com,Bob Jones,contributor
      newuser1,newuser1@domain.com,New User,author
      admin,admin@domain.com,Existing User,administrator
      """

    When I run `wp user import-csv users.csv`
    Then STDOUT should not be empty

    When I run `wp user list --format=count`
    Then STDOUT should be:
      """
      3
      """

    When I run `wp user list --format=json`
    Then STDOUT should be JSON containing:
    """
    [{"user_login":"admin","display_name":"Existing User","user_email":"admin@domain.com","roles":"administrator"}]
    """
