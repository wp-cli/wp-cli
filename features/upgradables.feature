Feature: Manage WordPress themes and plugins

  Scenario Outline: Installing, upgrading and deleting a theme or plugin
    Given a WP install
    And download:
      | path                   | url        |
      | {CACHE_DIR}/<item>.zip | <zip_file> |
    And I run `wp <type> path`
    And save STDOUT as {CONTENT_DIR}

    # Install an out of date <item> from WordPress.org repository
    When I run `wp <type> install <item> --version=<version>`
    Then STDOUT should contain:
      """
      <type_name> installed successfully
      """

    When I run `wp <type> status`
    Then STDOUT should contain:
      """
      U = Update Available
      """

    When I run `wp <type> status <item>`
    Then STDOUT should contain:
      """
          Status: Inactive
          Version: <version> (Update available)
      """

    When I run `wp <type> update <item>`
    Then STDOUT should not be empty

    When I run `wp <type> update --all`
    Then STDOUT should not be empty

    When I run `wp <type> status <item>`
    Then STDOUT should not contain:
      """
      (Update available)
      """

    When I run `wp <type> delete <item>`
    Then STDOUT should contain:
      """
      Success: Deleted '<item>' <type>.
      """

    When I try `wp <type> status <item>`
    Then the return code should be 1
    And STDERR should not be empty


    # Install <item> from a local zip file
    When I run `wp <type> install {CACHE_DIR}/<item>.zip`
    Then STDOUT should contain:
      """
      <type_name> installed successfully.
      """
    And the <file_to_check> file should exist

    When I run `wp <type> delete <item>`
    Then STDOUT should contain:
      """
      Success: Deleted '<item>' <type>.
      """
    And the <file_to_check> file should not exist

    # Install <item> from a remote zip file (standard URL with no GET parameters)
    When I run `wp <type> install <zip_file>`
    Then STDOUT should contain:
      """
      <type_name> installed successfully.
      """
    And the <file_to_check> file should exist

    When I run `wp <type> delete <item>`
    Then STDOUT should contain:
      """
      Success: Deleted '<item>' <type>.
      """
    And the <file_to_check> file should not exist

    # Install <item> from a remote zip file (complex URL with GET parameters)
    When I run `wp <type> install '<zip_file>?AWSAccessKeyId=123&Expires=456&Signature=abcdef'`
    Then STDOUT should contain:
      """
      <type_name> installed successfully.
      """
    And the <file_to_check> file should exist

    When I run `wp <type> delete <item>`
    Then STDOUT should contain:
      """
      Success: Deleted '<item>' <type>.
      """
    And the <file_to_check> file should not exist

    When I run `wp <type> search <item> --per-page=1 --fields=name,slug`
    Then STDOUT should contain:
      """
      Showing 1 of
      """
    And STDOUT should end with a table containing rows:
      | name         | slug   |
      | <item_title> | <item> |

    Examples:
      | type   | type_name | item                    | item_title              | version | zip_file                                                              | file_to_check                                                    |
      | theme  | Theme     | p2                      | P2                      | 1.0.1   | http://wordpress.org/themes/download/p2.1.0.1.zip                     | {CONTENT_DIR}/p2/style.css                                        |
      | plugin | Plugin    | category-checklist-tree | Category Checklist Tree | 1.2     | http://downloads.wordpress.org/plugin/category-checklist-tree.1.2.zip | {CONTENT_DIR}/category-checklist-tree/category-checklist-tree.php |
