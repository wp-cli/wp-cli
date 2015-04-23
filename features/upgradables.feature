Feature: Manage WordPress themes and plugins

  Background:
    Given an empty cache

  Scenario Outline: Installing, upgrading and deleting a theme or plugin
    Given a WP install
    And I run `wp <type> path`
    And save STDOUT as {CONTENT_DIR}

    When I try `wp <type> is-installed <item>`
    Then the return code should be 1
    And STDERR should be empty

    When I try `wp <type> get <item>`
    Then the return code should be 1
    And STDERR should not be empty

    # Install an out of date <item> from WordPress.org repository
    When I run `wp <type> install <item> --version=<version>`
    Then STDOUT should contain:
      """
      <type_name> installed successfully
      """
    And the {SUITE_CACHE_DIR}/<type>/<item>-<version>.zip file should exist

    When I try `wp <type> is-installed <item>`
    Then the return code should be 0

    When I run `wp <type> get <item>`
    Then STDOUT should be a table containing rows:
      | Field | Value         |
      | title  | <item_title> |

    When I run `wp <type> get <item> --field=title`
    Then STDOUT should contain:
       """
       <item_title>
       """

    When I run `wp <type> get <item> --field=title --format=json`
    Then STDOUT should contain:
       """
       "<item_title>"
       """

    When I run `wp <type> list`
    Then STDOUT should be a table containing rows:
      | name   | status   | update    | version   |
      | <item> | inactive | available | <version> |

    When I run `wp <type> list --field=name`
    Then STDOUT should contain:
      """
      <item>
      """

    When I run `wp <type> list --field=name --format=json`
    Then STDOUT should be a JSON array containing:
      """
      ["<item>"]
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
    And save STDOUT 'Downloading update from .*\/<item>\.%s\.zip' as {NEW_VERSION}
    Then STDOUT should not be empty
    And the {SUITE_CACHE_DIR}/<type>/<item>-{NEW_VERSION}.zip file should exist

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


    # Install and update <item> from cache
    When I run `wp <type> install <item> --version=<version>`
    Then STDOUT should contain:
      """
      Using cached file '{SUITE_CACHE_DIR}/<type>/<item>-<version>.zip'...
      """

    When I run `wp <type> update <item>`
    Then STDOUT should contain:
      """
      Using cached file '{SUITE_CACHE_DIR}/<type>/<item>-{NEW_VERSION}.zip'...
      """

    When I run `wp <type> delete <item>`
    Then STDOUT should contain:
      """
      Success: Deleted '<item>' <type>.
      """
    And the <file_to_check> file should not exist


    # Install <item> from a local zip file
    When I run `wp <type> install {SUITE_CACHE_DIR}/<type>/<item>-<version>.zip`
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

    When I run `wp <type> search <item> --per-page=2 --fields=name,slug`
    Then STDOUT should contain:
      """
      Showing 2 of
      """
    And STDOUT should end with a table containing rows:
      | name         | slug   |
      | <item_title> | <item> |

    Examples:
      | type   | type_name | item                    | item_title              | version | zip_file                                                              | file_to_check                                                    |
      | theme  | Theme     | p2                      | P2                      | 1.0.1   | https://wordpress.org/themes/download/p2.1.0.1.zip                     | {CONTENT_DIR}/p2/style.css                                        |
      | plugin | Plugin    | category-checklist-tree | Category Checklist Tree | 1.2     | https://downloads.wordpress.org/plugin/category-checklist-tree.1.2.zip | {CONTENT_DIR}/category-checklist-tree/category-checklist-tree.php |
