Feature: Manage WordPress themes and plugins

  Scenario Outline: Upgrading a theme or plugin
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
      Version: <version> (Update available)
      """

    When I run `wp <type> update <item>`
    Then STDOUT should not be empty

    When I run `wp <type> status <item>`
    Then STDOUT should not contain:
      """
      (Update available)
      """

    Examples:
      | type   | item                    | version |
      | theme  | p2                      | 1.0.1   |
      | plugin | category-checklist-tree | 1.2     |
