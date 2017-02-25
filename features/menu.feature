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
      Deleted menu 'My Menu'.
      Success: Deleted 1 of 1 menus.
      """
    And the return code should be 0

    When I run `wp menu list --format=count`
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
      Deleted menu 'First Menu'.
      Deleted menu 'Second Menu'.
      Success: Deleted 2 of 2 menus.
      """
    And the return code should be 0

    When I run `wp menu list --format=count`
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

  Scenario: Errors when deleting menus
    When I try `wp menu delete "Your menu"`
    Then STDERR should be:
      """
      Warning: Couldn't delete menu 'Your menu'.
      Error: No menus deleted.
      """

    When I run `wp menu create "My Menu"`
    And I run `wp menu list --fields=name,slug`
    Then STDOUT should be a table containing rows:
      | name       | slug       |
      | My Menu    | my-menu    |

    When I try `wp menu delete "My Menu" "Your menu"`
    Then STDERR should be:
      """
      Warning: Couldn't delete menu 'Your menu'.
      Error: Only deleted 1 of 2 menus.
      """
    And the return code should be 1
