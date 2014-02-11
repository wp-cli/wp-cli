Feature: Manage WordPress menus

  Background:
    Given a WP install

  Scenario: Menu CRUD operations

    When I try `wp menu create "My Menu"`
    Then STDERR should be empty

    When I run `wp menu list --fields=name,slug`
    Then STDOUT should be a table containing rows:
      | name       | slug       |
      | My Menu    | my-menu    |

    When I try `wp menu delete "My Menu"`
    Then STDERR should be empty

    When I run `wp menu list --format=count`
    Then STDOUT should be:
    """
    0
    """
