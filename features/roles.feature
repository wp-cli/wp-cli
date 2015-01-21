Feature: Manage WordPress roles

  Background:
    Given a WP install

  Scenario: Role CRUD operations
    When I run `wp role list`
    Then STDOUT should be a table containing rows:
      | name       | role       |
      | Subscriber | subscriber |
      | Editor     | editor     |

  Scenario: Resetting a role
    When I run `wp role reset author`
    Then STDOUT should be:
      """
      Success: Reset 0/1 roles
      """

    When I run `wp cap remove author read`
    And I run `wp role reset author`
    Then STDOUT should be:
      """
      Success: Reset 1/1 roles
      """

    When I run `wp role reset author editor`
    Then STDOUT should be:
      """
      Success: Reset 0/2 roles
      """

    When I run `wp cap remove author read`
    And I run `wp role reset author editor`
    Then STDOUT should be:
      """
      Success: Reset 1/2 roles
      """

    When I run `wp role reset --all`
    Then STDOUT should be:
      """
      Success: All default roles reset.
      """