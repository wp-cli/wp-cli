Feature: Manage WordPress taxonomies

  Background:
    Given a WP install

  Scenario: Listing taxonomies
    When I run `wp taxonomy list --format=csv`
    Then STDOUT should be CSV containing:
      | name     | label      | description | public | hierarchical |
      | category | Categories |             | 1      | 1            |
      | post_tag | Tags       |             | 1      |              |

    When I run `wp taxonomy list --object_type=link --format=csv`
    Then STDOUT should be CSV containing:
      | name          | label           | description | public | hierarchical |
      | link_category | Link Categories |             |        |              |
