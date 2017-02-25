Feature: Manage Cap

  Background:
    Given a WP install

  Scenario: CRUD for cap
    When I run `wp cap list contributor | sort`
    Then STDOUT should be:
      """
      delete_posts
      edit_posts
      level_0
      level_1
      read
      """

    When I run `wp cap add contributor spectate`
    Then STDOUT should contain:
      """
      Success: Added 1 capability to 'contributor' role.
      """

    When I run `wp cap add contributor behold observe`
    Then STDOUT should contain:
      """
      Success: Added 2 capabilities to 'contributor' role.
      """

    When I run `wp cap list contributor`
    Then STDOUT should contain:
      """
      spectate
      """
    And STDOUT should contain:
      """
      behold
      """
    And STDOUT should contain:
      """
      observe
      """

    When I run `wp cap remove contributor spectate`
    Then STDOUT should contain:
      """
      Success: Removed 1 capability from 'contributor' role.
      """

    When I run `wp cap remove contributor behold observe`
    Then STDOUT should contain:
      """
      Success: Removed 2 capabilities from 'contributor' role.
      """

    When I run `wp cap list contributor`
    Then STDOUT should not contain:
      """
      spectate
      """
    And STDOUT should not contain:
      """
      behold
      """
    And STDOUT should not contain:
      """
      observe
      """

    When I try `wp cap add role-not-available spectate`
    Then STDERR should be:
      """
      Error: 'role-not-available' role not found.
      """
