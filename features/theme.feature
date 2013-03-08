Feature: Manage WordPress themes

  Scenario: Checking the theme list
    Given WP install

    When I run `wp theme status`
    Then it should run without errors
    And STDOUT should not be empty

  Scenario: Installing a theme
    Given WP install

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

    When I run `wp theme activate p2`
    Then it should run without errors
    And STDOUT should contain:
      """
      Success: Switched to 'P2' theme.
      """

    When I run `wp theme delete p2`
    Then it should run without errors

    When I run the previous command again
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: The theme 'p2' could not be found.
      """
