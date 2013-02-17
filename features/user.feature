Feature: Manage WordPress users

  Scenario: Creating/updating/deleting users
    Given WP install

    When I run `wp user create testuser testuser@example.com --porcelain`
    Then it should run without errors
    And STDOUT should match '%d'
    And save STDOUT as {USER_ID}

    When I run the previous command again
    Then the return code should be 1

    When I run `wp user update {USER_ID} --displayname=Foo`
    Then it should run without errors
    And STDOUT should be:
      """
      Success: Updated user {USER_ID}.
      """

    When I run `wp user delete {USER_ID}`
    Then it should run without errors

  Scenario: Generating users
    Given WP install

    # Delete all users
    When I run `wp user list --ids`
    And save STDOUT as {USER_IDS}
    And I run `wp user delete {USER_IDS}`
    When I run `wp user list --ids`
    Then it should run without errors
    And STDOUT should be:
      """
      """

    When I run `wp user generate --count=10`
    Then it should run without errors
 
    When I run `wp user list | wc -l`
    Then STDOUT should be:
      """
      11
      """
