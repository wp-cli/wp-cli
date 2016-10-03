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
