Feature: Manage network-wide custom fields.

  Scenario: Non-multisite
    Given a WP install

    When I try `wp network-meta`
    Then STDOUT should contain:
      """
      usage: wp network meta
      """

    When I try `wp network-meta get 1 site_admins`
    Then STDOUT should be empty
    And STDERR should contain:
      """
      This is not a multisite install.
      """
