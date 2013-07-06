Feature: Manage WordPress plugins

  Scenario: Create, activate and check plugin status
    Given a WP install
    And I run `wp plugin path`
    And save STDOUT as {PLUGIN_DIR}

    When I run `wp plugin scaffold zombieland --plugin_name="Zombieland"`
    Then STDOUT should not be empty
    And the {PLUGIN_DIR}/zombieland/zombieland.php file should exist

    When I run `wp plugin status zombieland`
    Then STDOUT should contain:
      """
      Plugin zombieland details:
          Name: Zombieland
          Status: Inactive
          Version: 0.1-alpha
          Author: YOUR NAME HERE
          Description: PLUGIN DESCRIPTION HERE
      """

    When I run `wp plugin activate zombieland`
    Then STDOUT should not be empty

    When I run `wp plugin status zombieland`
    Then STDOUT should contain:
      """
          Status: Active
      """

    When I run `wp plugin status`
    Then STDOUT should not be empty

    When I run `wp plugin list`
    Then STDOUT should be a table containing rows:
      | name       | status | update | version   |
      | zombieland | active | none   | 0.1-alpha |

    When I try `wp plugin uninstall zombieland`
    Then the return code should be 1
    And STDERR should contain:
      """
      The plugin is active.
      """

    When I run `wp plugin deactivate zombieland`
    Then STDOUT should not be empty

    When I run `wp plugin uninstall zombieland`
    Then STDOUT should contain:
      """
      Success: Uninstalled 'zombieland' plugin.
      """
    And the {PLUGIN_DIR}/zombieland file should not exist

    When I try the previous command again
    Then the return code should be 1
    And STDERR should contain:
      """
      The plugin 'zombieland' could not be found.
      """

  Scenario: Install plugins from WordPress.org repository, a local zip file, and remote zip files
    Given a WP install
    And I run `wp plugin path`
    And save STDOUT as {PLUGIN_DIR}
    And a local mp6 plugin zip file

    When I run `wp plugin delete akismet`
    Then STDOUT should contain:
    """
    Success: Deleted 'akismet' plugin.
    """
    And the {PLUGIN_DIR}/akismet file should not exist

    # Install plugin from WordPress.org repository
    When I run `wp plugin install akismet`
    Then STDOUT should contain:
    """
    Plugin installed successfully.
    """
    And the {PLUGIN_DIR}/akismet/akismet.php file should exist

    When I try the previous command again
    Then the return code should be 1
    And STDERR should contain:
    """
    Error: Latest version already installed.
    """

    When I run `wp plugin delete akismet`
    Then STDOUT should contain:
    """
    Success: Deleted 'akismet' plugin.
    """
    And the {PLUGIN_DIR}/akismet file should not exist

    # Install plugin from a local zip file
    When I run `wp plugin install {DOWNLOADED_PLUGIN_FILE}`
    Then STDOUT should contain:
    """
    Plugin installed successfully.
    """
    And the {PLUGIN_DIR}/mp6/mp6.php file should exist
    And the {DOWNLOADED_PLUGIN_FILE} file should exist

    When I run `wp plugin delete mp6`
    Then STDOUT should contain:
    """
    Success: Deleted 'mp6' plugin.
    """
    And the {PLUGIN_DIR}/mp6 file should not exist

    # Install plugin from remote ZIP file (standard URL with no GET parameters)
    When I run `wp plugin install http://downloads.wordpress.org/plugin/akismet.zip`
    Then STDOUT should contain:
    """
    Plugin installed successfully.
    """
    And the {PLUGIN_DIR}/akismet/akismet.php file should exist

    When I run `wp plugin delete akismet`
    Then STDOUT should contain:
    """
    Success: Deleted 'akismet' plugin.
    """
    And the {PLUGIN_DIR}/akismet file should not exist

    # Install plugin from remote ZIP file (complex URL with GET parameters)
    When I run `wp plugin install 'http://downloads.wordpress.org/plugin/akismet.zip?AWSAccessKeyId=123&Expires=456&Signature=abcdef'`
    Then STDOUT should contain:
    """
    Plugin installed successfully.
    """
    And the {PLUGIN_DIR}/akismet/akismet.php file should exist

    When I run `wp plugin delete akismet`
    Then STDOUT should contain:
    """
    Success: Deleted 'akismet' plugin.
    """
    And the {PLUGIN_DIR}/akismet file should not exist