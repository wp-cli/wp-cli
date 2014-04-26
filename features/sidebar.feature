Feature: Manage WordPress sidebars

  Scenario: List available sidebars
    Given a WP install

    When I run `wp theme install p2 --activate`
    Then STDOUT should not be empty

    When I run `wp sidebar list --fields=name,id`
    Then STDOUT should be a table containing rows:
      | name       | id        |
      | Sidebar    | sidebar-1 |
