Feature: Manage WordPress themes

  Scenario: Installing a theme
    Given a WP install

    When I run `wp theme install p2`
    Then it should run without errors

    When I run the previous command again
    Then the return code should be 1

    When I run `wp theme status p2`
    Then it should run without errors
    And STDOUT should contain:
      """
      Theme p2 details:
          Name: P2
      """

    When I run `wp theme path p2`
    Then it should run without errors
    And STDOUT should contain:
      """
      /themes/p2/style.css
      """

    When I run `wp option get stylesheet`
    Then it should run without errors
    And save STDOUT as {PREVIOUS_THEME}

    When I run `wp theme activate p2`
    Then it should run without errors
    And STDOUT should contain:
      """
      Success: Switched to 'P2' theme.
      """

    When I run `wp theme activate {PREVIOUS_THEME}`
    Then it should run without errors
    And STDOUT should not be empty

    When I run `wp theme delete p2`
    Then it should run without errors

    When I run the previous command again
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: The theme 'p2' could not be found.
      """

    When I run `wp theme list`
    Then it should run without errors
    And STDOUT should not be empty

  Scenario: Upgrading a theme
    Given a WP install
    And a P2 theme zip

    When I run `wp theme install {THEME_ZIP}`
    Then it should run without errors

    When I run `wp theme status`
    Then it should run without errors
    And STDOUT should contain:
      """
      U = Update Available
      """

    When I run `wp theme status p2`
    Then it should run without errors
    And STDOUT should contain:
      """
      Version: 1.0.1 (Update available)
      """

    When I run `wp theme update p2`
    Then it should run without errors

    When I run `wp theme status p2`
    Then it should run without errors
    And STDOUT should not contain:
      """
      (Update available)
      """
