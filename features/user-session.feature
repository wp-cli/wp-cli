Feature: Manage user session

  Background:
    Given a WP install

  @require-wp-4.0
  Scenario: Destroy user sessions
    When I run `wp eval 'wp_set_current_user(1);'`
    And I run `wp eval 'wp_set_auth_cookie(1);'`
    And I run `wp eval 'wp_set_current_user(1);'`
    And I run `wp eval 'wp_set_auth_cookie(1);'`
    And I run `wp user session list admin --format=count`
    Then STDOUT should be:
      """
      2
      """

    When I run `wp user session destroy admin`
    Then STDOUT should be:
      """
      Success: Destroyed session. 1 remaining.
      """

    When I run `wp user session list admin --format=count`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp user session destroy admin --all`
    Then STDOUT should be:
      """
      Success: Destroyed all sessions.
      """

    And I run `wp user session list admin --format=count`
    Then STDOUT should be:
      """
      0
      """
