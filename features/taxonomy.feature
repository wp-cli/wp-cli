Feature: Manage WordPress taxonomies

  Background:
    Given a WP install

  @require-wp-3.7
  Scenario: Listing taxonomies
    When I run `wp taxonomy list --format=csv`
    Then STDOUT should be CSV containing:
      | name     | label      | description | object_type | show_tagcloud | hierarchical | public |
      | category | Categories |             | post        | 1             | 1            | 1      |
      | post_tag | Tags       |             | post        | 1             |              | 1      |

    When I run `wp taxonomy list --object_type=link --format=csv`
    Then STDOUT should be CSV containing:
      | name          | label            | description | object_type | show_tagcloud | hierarchical | public |
      | link_category | Link Categories  |             | link        |               |              |        |
