Feature: Manage WordPress post types

  Background:
    Given a WP install

  Scenario: Listing post types
    When I run `wp post-type list --format=csv`
    Then STDOUT should be CSV containing:
  | name | label | description | hierarchical | public | capability_type |
  | post | Posts |             |              | 1      | post            |
  | page | Pages |             | 1            | 1      | page            |
