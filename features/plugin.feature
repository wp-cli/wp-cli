Feature: Manage WordPress plugins

  Background:
    Given a WP install

  Scenario: Create, activate and check plugin status
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

    When I run `wp plugin is-installed zombieland && echo "Zombieland"`
    Then STDOUT should contain:
      """
      Zombieland
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

  Scenario: Get details about an installed plugin

    When I run `wp plugin get akismet`
    Then STDOUT should be a table containing rows:
      | Field | Value          |
      | name  | akismet        |


    When I run `wp plugin get akismet --field=title`
    Then STDOUT should contain:
       """
       Akismet
       """

    When I run `wp plugin get akismet --field=title --format=json`
    Then STDOUT should contain:
       """
       "Akismet"
       """

