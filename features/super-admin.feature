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
    And I run `wp super-admin list`
    Then STDOUT should be:
    """
    admin
    superadmin
    """

    When I run `wp super-admin remove admin`
    And I run `wp super-admin list`
    Then STDOUT should be:
    """
    superadmin
    """
