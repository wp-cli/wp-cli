Feature: Manage WordPress roles

  Background:
    Given a WP install

  Scenario: Role CRUD operations
    When I run `wp role list`
    Then STDOUT should be a table containing rows:
      | name       | role       |
      | Subscriber | subscriber |
      | Editor     | editor     |
