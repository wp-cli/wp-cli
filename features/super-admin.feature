Feature: Manage super admins associated with a multisite instance

  Scenario: Add, list, and remove super admins.
    Given a WP multisite install
    When I run `wp user create superadmin superadmin@example.com`
    And I run `wp super-admin list`
    Then STDOUT should be:
      """
      admin
      """

    When I run `wp super-admin add superadmin`
    Then STDOUT should be:
      """
      Success: Granted super-admin capabilities to 1 user.
      """
    And the return code should be 0

    When I run `wp super-admin list`
    Then STDOUT should be:
      """
      admin
      superadmin
      """

    When I run `wp super-admin add superadmin`
    Then STDERR should be:
      """
      Warning: User 'superadmin' already has super-admin capabilities.
      """
    And STDOUT should be:
      """
      Success: Super admins remain unchanged.
      """
    And the return code should be 0

    When I run `wp super-admin list`
    Then STDOUT should be:
      """
      admin
      superadmin
      """

    When I run `wp super-admin list --format=table`
    Then STDOUT should be a table containing rows:
      | user_login |
      | admin      |
      | superadmin |

    When I run `wp super-admin remove admin`
    And I run `wp super-admin list`
    Then STDOUT should be:
      """
      superadmin
      """

    When I run `wp super-admin list --format=json`
    Then STDOUT should be:
      """
      [{"user_login":"superadmin"}]
      """

    When I try `wp super-admin add noadmin`
    Then STDERR should be:
      """
      Warning: Invalid user ID, email or login: 'noadmin'
      Error: Couldn't grant super-admin capabilities to 1 of 1 users.
      """
    And the return code should be 1

    When I try `wp super-admin add admin noadmin`
    Then STDERR should be:
      """
      Warning: Invalid user ID, email or login: 'noadmin'
      Error: Only granted super-admin capabilities to 1 of 2 users.
      """
    And the return code should be 1
