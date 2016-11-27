Feature: Update WP-CLI packages

  Background:
    When I run `wp package path`
    Then save STDOUT as {PACKAGE_PATH}

  Scenario: Updating WP-CLI packages runs successfully
    Given an empty directory

    When I run `wp package install danielbachhuber/wp-cli-reset-post-date-command`
    Then STDOUT should contain:
      """
      Success: Package installed.
      """
    Then STDERR should be empty

    When I run `wp package update`
    Then STDOUT should contain:
      """
      Using Composer to update packages...
      """
    And STDOUT should contain:
      """
      Packages updated.
      """
    And STDERR should be empty

  Scenario: Update a package with an update available
    Given an empty directory

    When I run `wp package install wp-cli/scaffold-package-command:0.1.0`
    Then STDOUT should contain:
      """
      Installing package wp-cli/scaffold-package-command (0.1.0)
      """
    And STDOUT should contain:
      """
      Success: Package installed.
      """

    When I run `cat {PACKAGE_PATH}/composer.json`
    Then STDOUT should contain:
      """
      "wp-cli/scaffold-package-command": "0.1.0"
      """

    When I run `wp help scaffold package`
    Then STDOUT should contain:
      """
      wp scaffold package <name>
      """

    When I run `wp package update`
    Then STDOUT should contain:
      """
      Nothing to install or update
      """
    And STDOUT should contain:
      """
      Success: Packages updated.
      """

    When I run `wp package list --fields=name,update`
    Then STDOUT should be a table containing rows:
      | name                            | update    |
      | wp-cli/scaffold-package-command | available |

    When I run `sed -i.bak s/0.1.0/\>=0.1.0/g {PACKAGE_PATH}/composer.json`
    Then the return code should be 0

    When I run `cat {PACKAGE_PATH}/composer.json`
    Then STDOUT should contain:
      """
      "wp-cli/scaffold-package-command": ">=0.1.0"
      """

    When I run `wp package list --fields=name,update`
    Then STDOUT should be a table containing rows:
      | name                            | update     |
      | wp-cli/scaffold-package-command | available  |

    When I run `wp package update`
    Then STDOUT should contain:
      """
      Writing lock file
      """
    And STDOUT should contain:
      """
      Success: Packages updated.
      """
    And STDOUT should not contain:
      """
      Nothing to install or update
      """

    When I run `wp package list --fields=name,update`
    Then STDOUT should be a table containing rows:
      | name                            | update  |
      | wp-cli/scaffold-package-command | none    |

    When I run `wp package update`
    Then STDOUT should contain:
      """
      Nothing to install or update
      """
    And STDOUT should contain:
      """
      Success: Packages updated.
      """
