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

    When I run `wp theme update --all`
    Then STDOUT should not be empty

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

    When I run `wp theme activate p2`
    Then STDOUT should be:
      """
      Success: The 'P2' theme is already active.
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

  Scenario: Enabling and disabling a theme
  	Given a WP multisite install

    When I run `wp theme install p2`
    Then STDOUT should not be empty

    When I try `wp option get allowedthemes`
    Then the return code should be 1
    And STDERR should be empty

    When I run `wp theme enable p2`
    Then STDOUT should contain:
       """
       Success: Enabled the 'P2' theme.
       """

    When I run `wp option get allowedthemes`
    Then STDOUT should contain:
       """
       'p2' => true
       """

    When I run `wp theme disable p2`
    Then STDOUT should contain:
       """
       Success: Disabled the 'P2' theme.
       """

    When I run `wp option get allowedthemes`
    Then STDOUT should not contain:
       """
       'p2' => true
       """

    When I run `wp theme enable p2 --activate`
    Then STDOUT should contain:
       """
       Success: Enabled the 'P2' theme.
       Success: Switched to 'P2' theme.
       """

    When I run `wp network-meta get 1 allowedthemes`
    Then STDOUT should not contain:
       """
       'p2' => true
       """

    When I run `wp theme enable p2 --network`
    Then STDOUT should contain:
       """
       Success: Network enabled the 'P2' theme.
       """

    When I run `wp network-meta get 1 allowedthemes`
    Then STDOUT should contain:
       """
       'p2' => true
       """

    When I run `wp theme disable p2 --network`
    Then STDOUT should contain:
       """
       Success: Network disabled the 'P2' theme.
       """

    When I run `wp network-meta get 1 allowedthemes`
    Then STDOUT should not contain:
       """
       'p2' => true
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
