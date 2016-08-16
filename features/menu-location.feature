Feature: Manage WordPress menu locations

  Background:
    Given a WP install

  Scenario: Assign / remove location from a menu

    When I run `wp theme install p2 --activate`
    And I run `wp menu location list`
    Then STDOUT should be a table containing rows:
      | location       | description        |
      | primary        | Primary Menu       |

    When I run `wp menu create "Primary Menu"`
    And I run `wp menu location assign primary-menu primary`
    And I run `wp menu list --fields=slug,locations`
    Then STDOUT should be a table containing rows:
      | slug            | locations       |
      | primary-menu    | primary         |

    When I run `wp menu location list --format=ids`
    Then STDOUT should be:
      """
      primary
      """

    When I run `wp menu location remove primary-menu primary`
    And I run `wp menu list --fields=slug,locations`
    Then STDOUT should be a table containing rows:
      | slug            | locations       |
      | primary-menu    |                 |
