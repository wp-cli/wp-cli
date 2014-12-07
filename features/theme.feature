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

    When I run `wp theme path twentyfourteen --dir`
    Then STDOUT should contain:
       """
       wp-content/themes/twentyfourteen
       """

  Scenario: Activate an already active theme
    Given a WP install

    When I run `wp theme activate twentythirteen`
    Then STDOUT should be:
      """
      Success: Switched to 'Twenty Thirteen' theme.
      """

    When I run `wp theme activate twentythirteen`
    Then STDOUT should be:
      """
      Success: The 'Twenty Thirteen' theme is already active.
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

    When I run `wp theme enable twentythirteen`
    Then STDOUT should contain:
       """
       Success: Enabled the 'Twenty Thirteen' theme.
       """

    When I run `wp option get allowedthemes`
    Then STDOUT should contain:
       """
       'twentythirteen' => true
       """

    When I run `wp theme disable twentythirteen`
    Then STDOUT should contain:
       """
       Success: Disabled the 'Twenty Thirteen' theme.
       """

    When I run `wp option get allowedthemes`
    Then STDOUT should not contain:
       """
       'twentythirteen' => true
       """

    When I run `wp theme enable twentythirteen --activate`
    Then STDOUT should contain:
       """
       Success: Enabled the 'Twenty Thirteen' theme.
       Success: Switched to 'Twenty Thirteen' theme.
       """

    When I run `wp network-meta get 1 allowedthemes`
    Then STDOUT should not contain:
       """
       'twentythirteen' => true
       """

    When I run `wp theme enable twentythirteen --network`
    Then STDOUT should contain:
       """
       Success: Network enabled the 'Twenty Thirteen' theme.
       """

    When I run `wp network-meta get 1 allowedthemes`
    Then STDOUT should contain:
       """
       'twentythirteen' => true
       """

    When I run `wp theme disable twentythirteen --network`
    Then STDOUT should contain:
       """
       Success: Network disabled the 'Twenty Thirteen' theme.
       """

    When I run `wp network-meta get 1 allowedthemes`
    Then STDOUT should not contain:
       """
       'twentythirteen' => true
       """

  Scenario: Enabling and disabling a theme without multisite
  	Given a WP install

    When I try `wp theme enable twentythirteen`
    Then STDERR should be:
      """
      Error: This is not a multisite install.
      """

    When I try `wp theme disable twentythirteen`
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
