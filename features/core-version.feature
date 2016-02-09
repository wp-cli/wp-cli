Feature: Find version for WordPress install

  Scenario: Verify core version
    Given a WP install
    And I run `wp core download --version=4.4.2 --force`

    When I run `wp core version`
    Then STDOUT should be:
      """
      4.4.2
      """

    When I run `wp core version --extra`
    Then STDOUT should be:
      """
      WordPress version: 4.4.2
      Database revision: 35700
      TinyMCE version:   4.208 (4208-20151113)
      """