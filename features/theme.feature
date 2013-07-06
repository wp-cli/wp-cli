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


  Scenario: Install themes from WordPress.org repository, a local zip file, and remote zip files
    Given a WP install
    And I run `wp theme path`
    And save STDOUT as {THEME_DIR}
    And a local classic theme zip file

    # Install theme from WordPress.org repository
    When I run `wp theme install classic`
    Then STDOUT should contain:
    """
    Theme installed successfully.
    """
    And the {THEME_DIR}/classic/style.css file should exist

    When I try the previous command again
    Then the return code should be 1
    And STDERR should contain:
    """
    Error: Theme already installed and up to date.
    """

    When I run `wp theme delete classic`
    Then STDOUT should contain:
    """
    Success: Deleted 'classic' theme.
    """
    And the {THEME_DIR}/classic file should not exist

    # Install Theme from a local zip file
    When I run `wp theme install {DOWNLOADED_THEME_FILE}`
    Then STDOUT should contain:
    """
    Theme installed successfully.
    """
    And the {THEME_DIR}/classic/style.css file should exist
    And the {DOWNLOADED_THEME_FILE} file should exist

    When I run `wp theme delete classic`
    Then STDOUT should contain:
    """
    Success: Deleted 'classic' theme.
    """
    And the {THEME_DIR}/classic file should not exist

    # Install Theme from remote ZIP file (standard URL with no GET parameters)
    When I run `wp theme install http://wordpress.org/themes/download/classic.1.6.zip`
    Then STDOUT should contain:
    """
    Theme installed successfully.
    """
    And the {THEME_DIR}/classic/style.css file should exist

    When I run `wp theme delete classic`
    Then STDOUT should contain:
    """
    Success: Deleted 'classic' theme.
    """
    And the {THEME_DIR}/classic file should not exist

    # Install Theme from remote ZIP file (complex URL with GET parameters)
    When I run `wp theme install 'http://wordpress.org/themes/download/classic.1.6.zip?AWSAccessKeyId=123&Expires=456&Signature=abcdef'`
    Then STDOUT should contain:
    """
    Theme installed successfully.
    """
    And the {THEME_DIR}/classic/style.css file should exist

    When I run `wp theme delete classic`
    Then STDOUT should contain:
    """
    Success: Deleted 'classic' theme.
    """
    And the {THEME_DIR}/classic file should not exist