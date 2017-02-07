Feature: Manage WordPress themes

  Scenario: Installing and deleting theme
    Given a WP install

    When I run `wp theme install p2`
    Then STDOUT should not be empty

    When I run `wp theme status p2`
    Then STDOUT should contain:
      """
      Theme p2 details:
          Name: P2
      """

    When I run `wp theme path p2`
    Then STDOUT should contain:
      """
      /themes/p2/style.css
      """

    When I run `wp option get stylesheet`
    Then save STDOUT as {PREVIOUS_THEME}

    When I run `wp theme activate p2`
    Then STDOUT should contain:
      """
      Success: Switched to 'P2' theme.
      """

    When I try `wp theme delete p2`
    Then STDERR should be:
      """
      Warning: Can't delete the currently active theme: p2
      Error: No themes deleted.
      """
    And STDOUT should be empty

    When I run `wp theme activate {PREVIOUS_THEME}`
    Then STDOUT should not be empty

    When I run `wp theme delete p2`
    Then STDOUT should not be empty

    When I try the previous command again
    Then STDERR should contain:
      """
      The 'p2' theme could not be found.
      """

    When I run `wp theme list`
    Then STDOUT should not be empty

  Scenario: Checking theme status without theme parameter
    Given a WP install

    When I run `wp theme install classic --activate`
    And I run `wp theme list --field=name --status=inactive | xargs wp theme delete`
    And I run `wp theme status`
    Then STDOUT should be:
      """
      1 installed theme:
        A classic 1.6

      Legend: A = Active
      """

  Scenario: Install a theme, activate, then force install an older version of the theme
    Given a WP install

    When I run `wp theme install p2 --version=1.4.2`
    Then STDOUT should not be empty

    When I run `wp theme list`
    Then STDOUT should be a table containing rows:
      | name  | status   | update    | version   |
      | p2    | inactive | available | 1.4.2     |

    When I run `wp theme activate p2`
    Then STDOUT should not be empty

    When I run `wp theme install p2 --version=1.4.1 --force`
    Then STDOUT should not be empty

    When I run `wp theme list`
    Then STDOUT should be a table containing rows:
      | name  | status   | update    | version   |
      | p2    | active   | available | 1.4.1     |

    When I try `wp theme update`
    Then STDERR should be:
      """
      Error: Please specify one or more themes, or use --all.
      """

    When I run `wp theme update --all --format=summary | grep 'updated successfully from'`
    Then STDOUT should contain:
      """
      P2 updated successfully from version 1.4.1 to version
      """

  Scenario: Get the path of an installed theme
    Given a WP install

    When I run `wp theme install p2`
    Then STDOUT should not be empty

    When I run `wp theme path p2 --dir`
    Then STDOUT should contain:
       """
       wp-content/themes/p2
       """

  Scenario: Activate an already active theme
    Given a WP install

    When I run `wp theme install p2`
    Then STDOUT should not be empty

    When I run `wp theme activate p2`
    Then STDOUT should be:
      """
      Success: Switched to 'P2' theme.
      """

    When I try `wp theme activate p2`
    Then STDERR should be:
      """
      Warning: The 'P2' theme is already active.
      """

  Scenario: Install a theme when the theme directory doesn't yet exist
    Given a WP install

    When I run `rm -rf wp-content/themes`
    And I run `if test -d wp-content/themes; then echo "fail"; fi`
    Then STDOUT should be empty

    When I run `wp theme install p2 --activate`
    Then STDOUT should not be empty

    When I run `wp theme list --fields=name,status`
    Then STDOUT should be a table containing rows:
      | name  | status   |
      | p2    | active   |

  Scenario: Attempt to activate or fetch a broken theme
    Given a WP install

    When I run `mkdir -pv wp-content/themes/myth`
    Then the wp-content/themes/myth directory should exist

    When I try `wp theme activate myth`
    Then STDERR should contain:
      """
      Error: Stylesheet is missing.
      """

    When I try `wp theme get myth`
    Then STDERR should contain:
      """
      Error: Stylesheet is missing.
      """

    When I try `wp theme status myth`
    Then STDERR should be:
      """
      Error: Stylesheet is missing.
      """

    When I run `wp theme install myth --force`
    Then STDOUT should contain:
      """
      Theme updated successfully.
      """

  Scenario: Enabling and disabling a theme
  	Given a WP multisite install
    And I run `wp theme install jolene`
    And I run `wp theme install biker`

    When I try `wp option get allowedthemes`
    Then the return code should be 1
    And STDERR should be empty

    When I run `wp theme enable biker`
    Then STDOUT should contain:
       """
       Success: Enabled the 'Biker' theme.
       """

    When I run `wp option get allowedthemes`
    Then STDOUT should contain:
       """
       'biker' => true
       """

    When I run `wp theme disable biker`
    Then STDOUT should contain:
       """
       Success: Disabled the 'Biker' theme.
       """

    When I run `wp option get allowedthemes`
    Then STDOUT should not contain:
       """
       'biker' => true
       """

    When I run `wp theme enable biker --activate`
    Then STDOUT should contain:
       """
       Success: Enabled the 'Biker' theme.
       Success: Switched to 'Biker' theme.
       """

    When I run `wp network-meta get 1 allowedthemes`
    Then STDOUT should not contain:
       """
       'biker' => true
       """

    When I run `wp theme enable biker --network`
    Then STDOUT should contain:
       """
       Success: Network enabled the 'Biker' theme.
       """

    When I run `wp network-meta get 1 allowedthemes`
    Then STDOUT should contain:
       """
       'biker' => true
       """

    When I run `wp theme disable biker --network`
    Then STDOUT should contain:
       """
       Success: Network disabled the 'Biker' theme.
       """

    When I run `wp network-meta get 1 allowedthemes`
    Then STDOUT should not contain:
       """
       'biker' => true
       """

  Scenario: Enabling and disabling a theme without multisite
  	Given a WP install

    When I try `wp theme enable p2`
    Then STDERR should be:
      """
      Error: This is not a multisite install.
      """

    When I try `wp theme disable p2`
    Then STDERR should be:
      """
      Error: This is not a multisite install.
      """

  Scenario: Install a theme, then update to a specific version of that theme
    Given a WP install

    When I run `wp theme install p2 --version=1.4.1`
    Then STDOUT should not be empty

    When I run `wp theme update p2 --version=1.4.2`
    Then STDOUT should not be empty

    When I run `wp theme list --fields=name,version`
    Then STDOUT should be a table containing rows:
      | name       | version   |
      | p2         | 1.4.2     |

  Scenario: Install and attempt to activate a child theme without its parent
    Given a WP install
    And I run `wp theme install biker`
    And I run `rm -rf wp-content/themes/jolene`

    When I try `wp theme activate biker`
    Then STDERR should contain:
      """
      Error: The parent theme is missing. Please install the "jolene" parent theme.
      """

  Scenario: List an active theme with its parent
    Given a WP install
    And I run `wp theme install jolene`
    And I run `wp theme install --activate biker`

    When I run `wp theme list --fields=name,status`
    Then STDOUT should be a table containing rows:
      | name          | status   |
      | biker         | active   |
      | jolene        | parent   |

  Scenario: When updating a theme --format should be the same when using --dry-run
    Given a WP install

    When I run `wp theme install --force twentytwelve --version=1.0`
    Then STDOUT should not be empty

    When I run `wp theme list --name=twentytwelve --field=update_version`
    And save STDOUT as {UPDATE_VERSION}

    When I run `wp theme update twentytwelve --format=summary --dry-run`
    Then STDOUT should contain:
      """
      Available theme updates:
      Twenty Twelve update from version 1.0 to version {UPDATE_VERSION}
      """

    When I run `wp theme update twentytwelve --format=json --dry-run`
    Then STDOUT should be JSON containing:
      """
      [{"name":"twentytwelve","status":"inactive","version":"1.0","update_version":"{UPDATE_VERSION}"}]
      """

    When I run `wp theme update twentytwelve --format=csv --dry-run`
    Then STDOUT should contain:
      """
      name,status,version,update_version
      twentytwelve,inactive,1.0,{UPDATE_VERSION}
      """

  Scenario: Check json and csv formats when updating a theme
    Given a WP install

    When I run `wp theme install --force twentytwelve --version=1.0`
    Then STDOUT should not be empty

    When I run `wp theme list --name=twentytwelve --field=update_version`
    And save STDOUT as {UPDATE_VERSION}

    When I run `wp theme update twentytwelve --format=json`
    Then STDOUT should contain:
      """
      [{"name":"twentytwelve","old_version":"1.0","new_version":"{UPDATE_VERSION}","status":"Updated"}]
      """

    When I run `wp theme install --force twentytwelve --version=1.0`
    Then STDOUT should not be empty

    When I run `wp theme update twentytwelve --format=csv`
    Then STDOUT should contain:
      """
      name,old_version,new_version,status
      twentytwelve,1.0,{UPDATE_VERSION},Updated
      """

  Scenario: Automatically install parent theme for a child theme
    Given a WP install

    When I try `wp theme status stargazer`
    Then STDERR should contain:
      """
      Error: The 'stargazer' theme could not be found.
      """

    When I run `wp theme install buntu`
    Then STDOUT should contain:
      """
      This theme requires a parent theme. Checking if it is installed
      """

    When I run `wp theme status stargazer`
    Then STDOUT should contain:
      """
      Theme stargazer details:
      """
    And STDERR should be empty
