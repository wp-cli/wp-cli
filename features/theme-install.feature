Feature: Install WordPress themes

  Scenario: Return code is 1 when one or more theme installations fail
    Given a WP install

    When I try `wp theme install p2 p2-not-a-theme`
    Then STDERR should be:
      """
      Warning: Couldn't find 'p2-not-a-theme' in the WordPress.org theme directory.
      Error: Only installed 1 of 2 themes.
      """
    And STDOUT should contain:
      """
      Installing P2
      """
    And STDOUT should contain:
      """
      Theme installed successfully.
      """
    And the return code should be 1

    When I run `wp theme install p2`
    Then STDOUT should be:
      """
      Success: Theme already installed.
      """
    And STDERR should be:
      """
      Warning: p2: Theme already installed.
      """
    And the return code should be 0

    When I try `wp theme install p2-not-a-theme`
    Then STDERR should be:
      """
      Warning: Couldn't find 'p2-not-a-theme' in the WordPress.org theme directory.
      Error: No themes installed.
      """
    And the return code should be 1
