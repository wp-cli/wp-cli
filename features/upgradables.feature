Feature: Manage WordPress themes and plugins

  Scenario Outline: Installing, upgrading and deleting a theme or plugin
    Given a WP install
    And I run `wp <type> install <item> --version=<version>`

    When I run `wp <type> status`
    Then STDOUT should contain:
      """
      U = Update Available
      """

    When I run `wp <type> status <item>`
    Then STDOUT should contain:
      """
          Status: Inactive
          Version: <version> (Update available)
      """

    When I run `wp <type> update <item>`
    Then STDOUT should not be empty

    When I run `wp <type> status <item>`
    Then STDOUT should not contain:
      """
      (Update available)
      """

    When I run `wp <type> delete <item>`
    Then STDOUT should contain:
      """
      Success: Deleted '<item>' <type>.
      """

    When I try `wp <type> status <item>`
    Then the return code should be 1
    And STDERR should not be empty

    Examples:
      | type   | item                    | version |
      | theme  | p2                      | 1.0.1   |
      | plugin | category-checklist-tree | 1.2     |
