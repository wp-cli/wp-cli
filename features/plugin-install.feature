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
      Renamed Github-based project from 'one-time-login-master' to 'one-time-login'.
      """
    And STDOUT should contain:
      """
      Plugin installed successfully.
      """
    And STDERR should be empty
    And the wp-content/plugins/one-time-login directory should exist
    And the wp-content/plugins/one-time-login-master directory should not exist

    When I try `wp plugin install https://github.com/runcommand/one-time-login/archive/master.zip`
    Then STDERR should contain:
      """
      Warning: Destination folder already exists
      """
    And the wp-content/plugins/one-time-login directory should exist
    And the wp-content/plugins/one-time-login-master directory should not exist

    When I run `wp plugin install https://github.com/runcommand/one-time-login/archive/master.zip --force`
    Then STDOUT should contain:
      """
      Plugin updated successfully.
      """
    And the wp-content/plugins/one-time-login directory should exist
    And the wp-content/plugins/one-time-login-master directory should not exist

  Scenario: Don't attempt to rename ZIPs uploaded to GitHub's releases page
    Given a WP install

    When I run `wp plugin install https://github.com/danielbachhuber/one-time-login/releases/download/v0.1.2/one-time-login.0.1.2.zip`
    Then STDOUT should contain:
      """
      Plugin installed successfully.
      """
    And STDOUT should not contain:
      """
      Renamed Github-based project from
      """
    And STDERR should be empty
    And the wp-content/plugins/one-time-login directory should exist

  Scenario: Installing respects WP_PROXY_HOST and WP_PROXY_PORT
    Given a WP install
    And a invalid-proxy-details.php file:
      """
      <?php
      define( 'WP_PROXY_HOST', 'https://wp-cli.org' );
      define( 'WP_PROXY_PORT', '443' );
      """

    When I try `wp --require=invalid-proxy-details.php plugin install edit-flow`
    Then STDERR should contain:
      """
      Warning: edit-flow: An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration.
      """
    And STDOUT should be empty

    When I run `wp plugin install edit-flow`
    Then STDOUT should contain:
      """
      Plugin installed successfully.
      """
    And STDERR should be empty

  Scenario: Return code is 1 when one or more plugin installations fail
    Given a WP install

    When I try `wp plugin install user-switching user-switching-not-a-plugin`
    Then STDERR should be:
      """
      Warning: Couldn't find 'user-switching-not-a-plugin' in the WordPress.org plugin directory.
      Error: Only installed 1 of 2 plugins.
      """
    And STDOUT should contain:
      """
      Installing User Switching
      """
    And STDOUT should contain:
      """
      Plugin installed successfully.
      """
    And the return code should be 1

    When I run `wp plugin install user-switching`
    Then STDOUT should be:
      """
      Success: Plugin already installed.
      """
    And STDERR should be:
      """
      Warning: user-switching: Plugin already installed.
      """
    And the return code should be 0

    When I try `wp plugin install user-switching-not-a-plugin`
    Then STDERR should be:
      """
      Warning: Couldn't find 'user-switching-not-a-plugin' in the WordPress.org plugin directory.
      Error: No plugins installed.
      """
    And the return code should be 1

  Scenario: Paths aren't backslashed when destination folder already exists
    Given a WP install

    When I run `pwd`
    Then save STDOUT as {WORKING_DIR}

    When I run `rm wp-content/plugins/akismet/akismet.php`
    Then the return code should be 0

    When I try `wp plugin install akismet`
    Then STDERR should contain:
      """
      Warning: Destination folder already exists. "{WORKING_DIR}/wp-content/plugins/akismet/"
      """
