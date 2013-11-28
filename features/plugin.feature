Feature: Manage WordPress plugins

  Scenario: Create, activate and check plugin status
    Given a WP install
    And I run `wp plugin path`
    And save STDOUT as {PLUGIN_DIR}

    When I run `wp plugin scaffold --skip-tests plugin1`
    Then STDOUT should not be empty
    And the {PLUGIN_DIR}/plugin1/plugin1.php file should exist
    And the {PLUGIN_DIR}/zombieland/phpunit.xml file should not exist

    When I run `wp plugin scaffold zombieland --plugin_name="Zombieland"`
    Then STDOUT should not be empty
    And the {PLUGIN_DIR}/zombieland/zombieland.php file should exist
    And the {PLUGIN_DIR}/zombieland/phpunit.xml file should exist

    # Check that the inner-plugin is not picked up
    When I run `mv {PLUGIN_DIR}/plugin1 {PLUGIN_DIR}/zombieland/`
    And I run `wp plugin status zombieland`
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
    Then STDERR should contain:
      """
      The 'zombieland' plugin is active.
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
    Then STDERR should contain:
      """
      The 'zombieland' plugin could not be found.
      """

  Scenario: Install a plugin, activate, then force install an older version of the plugin
    Given a WP install

    When I run `wp plugin install akismet --version=2.5.7 --force`
    Then STDOUT should not be empty

    When I run `wp plugin list`
    Then STDOUT should be a table containing rows:
      | name       | status   | update    | version   |
      | akismet    | inactive | available | 2.5.7     |

    When I run `wp plugin activate akismet`
    Then STDOUT should not be empty

    When I run `wp plugin install akismet --version=2.5.6 --force`
    Then STDOUT should not be empty

    When I run `wp plugin list`
    Then STDOUT should be a table containing rows:
      | name       | status   | update    | version   |
      | akismet    | active   | available | 2.5.6     |

  Scenario: Activate a network-only plugin
    Given a WP multisite install
    And a wp-content/plugins/network-only.php file:
      """
      // Plugin Name: Example Plugin
      // Network: true
      """
    When I run `wp plugin activate network-only`
    And I run `wp plugin status network-only`
    Then STDOUT should contain:
      """
          Status: Network Active
      """

  Scenario: List plugins
    Given a WP install

    When I run `wp plugin activate akismet hello`
    Then STDOUT should not be empty

    When I run `wp plugin list --status=inactive --field=name`
    Then STDOUT should be empty

    When I run `wp plugin list --status=active --fields=name,status`
    Then STDOUT should be a table containing rows:
      | name       | status   |
      | akismet    | active   |
