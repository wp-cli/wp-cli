Feature: Manage WordPress installation

  Scenario: Non-multisite
    Given a WP install

    When I try `wp site-meta`
    Then STDOUT should contain:
      """
      usage: wp site-meta
      """

    When I try `wp site-meta get 1 site_admins`
    Then STDOUT should be empty
    And STDERR should contain:
      """
      This is not a multisite install.
      """
