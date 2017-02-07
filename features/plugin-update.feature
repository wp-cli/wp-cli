Feature: Update WordPress plugins

  Scenario: Updating plugin with invalid version shouldn't remove the old version
    Given a WP install

    When I run `wp plugin install akismet --version=2.5.6 --force`
    Then STDOUT should not be empty

    When I run `wp plugin list --name=akismet --field=update_version`
    Then STDOUT should not be empty
    And save STDOUT as {UPDATE_VERSION}

    When I run `wp plugin list`
    Then STDOUT should be a table containing rows:
      | name       | status   | update    | version   |
      | akismet    | inactive | available | 2.5.6     |

    When I try `wp plugin update akismet --version=2.9.0`
    Then STDERR should be:
      """
      Error: Can't find the requested plugin's version 2.9.0 in the WordPress.org plugin repository (HTTP code 404).
      """

    When I run `wp plugin list`
    Then STDOUT should be a table containing rows:
      | name       | status   | update    | version   |
      | akismet    | inactive | available | 2.5.6     |

    When I run `wp plugin update akismet`
    Then STDOUT should not be empty

    When I run `wp plugin list`
    Then STDOUT should be a table containing rows:
      | name       | status   | update    | version           |
      | akismet    | inactive | none      | {UPDATE_VERSION}  |
