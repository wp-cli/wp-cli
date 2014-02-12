Feature: Manage WordPress menus

  Background:
    Given a WP install

  Scenario: Menu CRUD operations

    When I run `wp menu create "My Menu"`
    And I run `wp menu list --fields=name,slug`
    Then STDOUT should be a table containing rows:
      | name       | slug       |
      | My Menu    | my-menu    |

    When I run `wp menu delete "My Menu"`
    And I run `wp menu list --format=count`
    Then STDOUT should be:
    """
    0
    """
