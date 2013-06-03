Feature: Manage WordPress themes

  Scenario: Installing a theme
    Given a WP install

    When I run `wp theme install p2`
    Then STDOUT should not be empty

    When I try the previous command again
    Then the return code should be 1

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

    When I run `wp theme activate {PREVIOUS_THEME}`
    Then STDOUT should not be empty

    When I run `wp theme delete p2`
    Then STDOUT should not be empty

    When I try the previous command again
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: The theme 'p2' could not be found.
      """

    When I run `wp theme list`
    Then STDOUT should not be empty

