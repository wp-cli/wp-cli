Feature: Manage WordPress users

  Scenario: Creating/deleting users
    Given WP install

    When I run `wp user create testuser testuser@example.com --porcelain`
    Then the return code should be 0
    And STDOUT should match '%d'

    When I run `wp user delete <STDOUT>`
    Then the return code should be 0
    And STDOUT should not be empty

  Scenario: Generating users
    Given WP install

    # Delete all users
    When I run `wp user list --ids`
    And I run `wp user delete <STDOUT>`
    When I run `wp user list --ids`
    Then the return code should be 0
    And STDOUT should be:
      """
      """

    When I run `wp user generate --count=10`
    Then the return code should be 0
 
    When I run `wp user list | wc -l`
    Then STDOUT should be:
      """
      11
      """
