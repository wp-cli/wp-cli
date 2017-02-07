Feature: Manage WordPress post types

  Background:
    Given a WP install

  Scenario: Listing post types
    When I run `wp post-type list --format=csv`
    Then STDOUT should be CSV containing:
  | name | label | description | hierarchical | public | capability_type |
  | post | Posts |             |              | 1      | post            |
  | page | Pages |             | 1            | 1      | page            |

  Scenario: Get a post type
    When I try `wp post-type get invalid-post-type`
    Then STDERR should be:
      """
      Error: Post type invalid-post-type doesn't exist.
      """

    When I run `wp post-type get page`
    Then STDOUT should be a table containing rows:
      | Field       | Value     |
      | name        | page      |
      | label       | Pages     |
