Feature: Manage WordPress sidebars

  Scenario: List available sidebars
    Given a WP install

    When I run `wp theme install hexa --activate`
    Then STDOUT should not be empty

    When I run `wp sidebar list --fields=name,id`
    Then STDOUT should be a table containing rows:
      | name       | id        |
      | Sidebar 1  | sidebar-1 |

    When I run `wp sidebar list --format=ids`
    Then STDOUT should be:
    """
    sidebar-1 sidebar-2 sidebar-3 wp_inactive_widgets
    """

    When I run `wp sidebar list --format=count`
    Then STDOUT should be:
    """
    4
    """
