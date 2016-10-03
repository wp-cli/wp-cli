Feature: List WordPress options

  Scenario: Using the `--transients` flag
    Given a WP install
    And I run `wp transient set wp_transient_flag wp_transient_flag`

    When I run `wp option list --no-transients`
    Then STDOUT should not contain:
      """
      wp_transient_flag
      """
    And STDOUT should not contain:
      """
      _transient
      """
    And STDOUT should contain:
      """
      siteurl
      """

    When I run `wp option list --transients`
    Then STDOUT should contain:
      """
      wp_transient_flag
      """
    And STDOUT should contain:
      """
      _transient
      """
    And STDOUT should not contain:
      """
      siteurl
      """
