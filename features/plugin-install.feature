Feature: Install WordPress plugins

  Scenario: Branch names should be removed from Github projects
    Given a WP install

    When I run `wp plugin install https://github.com/runcommand/one-time-login/archive/master.zip --activate`
    Then STDOUT should contain:
      """
      Downloading install package from https://github.com/runcommand/one-time-login/archive/master.zip
      """
    And STDOUT should contain:
      """
      Moved Github-based project from 'one-time-login-master' to 'one-time-login'.
      """
    And STDOUT should contain:
      """
      Plugin installed successfully.
      """
    And STDERR should be empty
    And the wp-content/plugins/one-time-login directory should exist
    And the wp-content/plugins/one-time-login-master directory should not exist
