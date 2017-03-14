Feature: Install WordPress themes

  Scenario: Return code is 1 when one or more theme installations fail
    Given a WP install

    When I try `wp theme install hexa hexa-not-a-theme`
    Then STDERR should be:
      """
      Warning: Couldn't find 'hexa-not-a-theme' in the WordPress.org theme directory.
      Error: Only installed 1 of 2 themes.
      """
    And STDOUT should contain:
      """
      Installing Hexa
      """
    And STDOUT should contain:
      """
      Theme installed successfully.
      """
    And the return code should be 1

    When I run `wp theme install hexa`
    Then STDOUT should be:
      """
      Success: Theme already installed.
      """
    And STDERR should be:
      """
      Warning: hexa: Theme already installed.
      """
    And the return code should be 0

    When I try `wp theme install hexa-not-a-theme`
    Then STDERR should be:
      """
      Warning: Couldn't find 'hexa-not-a-theme' in the WordPress.org theme directory.
      Error: No themes installed.
      """
    And the return code should be 1

  Scenario: Ensure automatic parent theme installation uses http cacher
    Given a WP install
    And an empty cache

    When I run `wp theme install stargazer`
    Then STDOUT should contain:
      """
      Success: Installed 1 of 1 themes.
      """
    And STDOUT should not contain:
      """
      Using cached file
      """

    When I run `wp theme uninstall stargazer`
    Then STDOUT should contain:
      """
      Success: Deleted 1 of 1 themes.
      """

    When I run `wp theme install buntu`
    Then STDOUT should contain:
      """
      Success: Installed 1 of 1 themes.
      """
    And STDOUT should contain:
      """
      This theme requires a parent theme.
      """
    And STDOUT should contain:
      """
      Using cached file
      """
