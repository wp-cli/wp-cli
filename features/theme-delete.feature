Feature: Delete WordPress themes

  Background:
    Given a WP install
    And I run `wp theme install hexa`

  Scenario: Delete an installed theme
    When I run `wp theme delete hexa`
    Then STDOUT should be:
      """
      Deleted 'hexa' theme.
      Success: Deleted 1 of 1 themes.
      """
    And the return code should be 0

  Scenario: Attempting to delete a theme that doesn't exist
    When I run `wp theme delete hexa`
    Then STDOUT should not be empty

    When I run the previous command again
    Then STDOUT should be:
      """
      Success: Theme already deleted.
      """
    And STDERR should be:
      """
      Warning: The 'hexa' theme could not be found.
      """
    And the return code should be 0
