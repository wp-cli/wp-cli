Feature: Manage WordPress sidebars

  Scenario: List available sidebars
    Given a WP install

    When I run `wp theme install p2 --activate`
    Then STDOUT should not be empty

    When I run `wp sidebar list --fields=name,id`
    Then STDOUT should be a table containing rows:
      | name       | id        |
      | Sidebar    | sidebar-1 |

    When I run `wp sidebar list --format=ids`
    Then STDOUT should be:
    """
    sidebar-1 wp_inactive_widgets
    """

    When I run `wp sidebar list --format=count`
    Then STDOUT should be:
    """
    2
    """
