Feature: Manage WordPress roles

  Background:
    Given a WP install

  Scenario: Role CRUD operations
    When I run `wp role list`
    Then STDOUT should be a table containing rows:
      | name       | role       |
      | Subscriber | subscriber |
      | Editor     | editor     |

    When I run `wp role create reporter Reporter`
    Then STDOUT should be:
      """
      Success: Role with key reporter created.
      """

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

  Scenario: Cloning a role
    When I try `wp role create reporter Reporter --clone=no-role`
    Then STDERR should be:
      """
      Error: 'no-role' role not found.
      """

    When I run `wp role create reporter Reporter --clone=author`
    Then STDOUT should be:
      """
      Success: Role with key reporter created. Cloned capabilities from author.
      """

    When I run `wp role list`
    Then STDOUT should be a table containing rows:
      | name       | role       |
      | Reporter   | reporter   |

    When I run `wp cap list reporter`
    Then STDOUT should be:
      """
      upload_files
      edit_posts
      edit_published_posts
      publish_posts
      read
      level_2
      level_1
      level_0
      delete_posts
      delete_published_posts
      """
