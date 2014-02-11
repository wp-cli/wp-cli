Feature: Manage super admins associated with a multisite instance

  Scenario: Add, list, and remove super admins.
    Given a WP multisite install
    When I run `wp user create superadmin superadmin@example.com`
    Then STDERR should be empty
    And I run `wp super-admin list`
    Then STDOUT should contain:
    """
    admin
    """

    When I run `wp super-admin add superadmin`
    Then STDERR should be empty
    And I run `wp super-admin list`
    Then STDOUT should contain:
    """
    admin
    superadmin
    """

    When I run `wp super-admin remove admin`
    And I run `wp super-admin list`
    Then STDOUT should contain:
    """
    superadmin
    """
