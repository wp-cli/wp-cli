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
    Then STDOUT should be:
      """
      Success: 1 menu deleted.
      """
    And I run `wp menu list --format=count`
    Then STDOUT should be:
      """
      0
      """

    When I run `wp menu create "First Menu"`
    And I run `wp menu create "Second Menu"`
    And I run `wp menu list --fields=name,slug`
    Then STDOUT should be a table containing rows:
      | name           | slug           |
      | First Menu     | first-menu     |
      | Second Menu    | second-menu    |

    When I run `wp menu delete "First Menu" "Second Menu"`
    Then STDOUT should be:
      """
      Success: 2 menus deleted.
      """
    And I run `wp menu list --format=count`
    Then STDOUT should be:
      """
      0
      """

    When I run `wp menu create "First Menu"`
    And I run `wp menu list --format=ids`
    Then STDOUT should be:
      """
      5
      """
