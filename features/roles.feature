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
      No changes necessary for 'author' role.
      Success: Roles reset 0/1.
      """

    When I run `wp cap remove author read`
    And I run `wp role reset author`
    Then STDOUT should be:
      """
      Restored 10 capabilities to and removed 9 capabilities from 'author' role.
      Success: Role reset 1/1.
      """

    When I run `wp role reset author editor`
    Then STDOUT should be:
      """
      No changes necessary for 'author' role.
      No changes necessary for 'editor' role.
      Success: Roles reset 0/2.
      """

    When I run `wp cap remove author read`
    And I run `wp role reset author editor`
    Then STDOUT should be:
      """
      Restored 10 capabilities to and removed 9 capabilities from 'author' role.
      No changes necessary for 'editor' role.
      Success: Role reset 1/2.
      """

    When I run `wp role reset --all`
    Then STDOUT should be:
      """
      No changes necessary for 'administrator' role.
      No changes necessary for 'editor' role.
      No changes necessary for 'author' role.
      No changes necessary for 'contributor' role.
      No changes necessary for 'subscriber' role.
      Success: Roles reset 0/5.
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

    When I run `wp cap list reporter --format=count`
    Then STDOUT should be:
      """
      10
      """
