Feature: Manage WordPress users

  Scenario: Creating/updating/deleting users
    Given a WP install

    When I run `wp user create testuser testuser@example.com --porcelain`
    Then STDOUT should match '%d'
    And save STDOUT as {USER_ID}

    When I try the previous command again
    Then the return code should be 1

    When I run `wp user update {USER_ID} --displayname=Foo`
    Then STDOUT should be:
      """
      Success: Updated user {USER_ID}.
      """

    When I run `wp user delete {USER_ID}`

  Scenario: Generating users
    Given a WP install

    # Delete all users
    When I run `wp user list --format=ids`
    And save STDOUT as {USER_IDS}
    And I run `wp user delete {USER_IDS}`
    And I run `wp user list --format=ids`
    Then STDOUT should be empty

    When I run `wp user generate --count=10`
 
    When I run `wp user list | wc -l | tr -d ' '`
    Then STDOUT should be:
      """
      11
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

    When I run `wp user list | wc -l | tr -d ' '`
    Then STDOUT should be:
      """
      4
      """

    When I run `wp user list --format=json`
    And STDOUT should be JSON containing:
    """
    [{"user_login":"admin","display_name":"Existing User","user_email":"admin@domain.com","roles":"administrator"}]
    """
