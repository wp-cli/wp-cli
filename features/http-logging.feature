Feature: HTTP request logging

  Scenario: HTTP requests are logged when WordPress isn't loaded
    Given an empty directory

    When I try `wp cli check-update --debug=http`
    Then STDERR should contain:
      """
      Debug: HTTP GET request to https://api.github.com
      """
    And the return code should be 0

  Scenario: HTTP requests are logged with --debug=http flag
    Given a WP installation
    And a http-test.php file:
      """
      <?php
      WP_CLI::add_command( 'http-test', function() {
        // Make a test HTTP request using WP-CLI's http_request
        try {
          WP_CLI\Utils\http_request( 'GET', 'https://api.wordpress.org/core/version-check/1.7/', null, [], [ 'timeout' => 5 ] );
          WP_CLI::success( 'HTTP request completed' );
        } catch ( Exception $e ) {
          WP_CLI::error( 'HTTP request failed: ' . $e->getMessage() );
        }
      });
      """
    And a wp-cli.yml file:
      """
      require:
        - http-test.php
      """

    When I try `wp http-test --debug=http`
    Then STDERR should contain:
      """
      Debug: HTTP GET request to https://api.wordpress.org/core/version-check/1.7/
      """
    And the return code should be 0

  Scenario: HTTP requests are not logged without debug flag
    Given a WP installation
    And a http-test.php file:
      """
      <?php
      WP_CLI::add_command( 'http-test', function() {
        // Make a test HTTP request
        try {
          WP_CLI\Utils\http_request( 'GET', 'https://api.wordpress.org/core/version-check/1.7/', null, [], [ 'timeout' => 5 ] );
          WP_CLI::success( 'HTTP request completed' );
        } catch ( Exception $e ) {
          WP_CLI::error( 'HTTP request failed: ' . $e->getMessage() );
        }
      });
      """
    And a wp-cli.yml file:
      """
      require:
        - http-test.php
      """

    When I run `wp http-test`
    Then STDERR should not contain:
      """
      HTTP GET request to
      """
    And the return code should be 0

  Scenario: Different HTTP methods are logged correctly
    Given a WP installation
    And a http-methods-test.php file:
      """
      <?php
      WP_CLI::add_command( 'http-methods-test', function() {
        // Test different HTTP methods
        $test_url = 'https://httpbin.org/';

        // GET request
        try {
          WP_CLI\Utils\http_request( 'GET', $test_url . 'get', null, [], [ 'timeout' => 5 ] );
        } catch ( Exception $e ) {
          // Ignore errors for this test
        }

        // POST request
        try {
          WP_CLI\Utils\http_request( 'POST', $test_url . 'post', ['test' => 'data'], [], [ 'timeout' => 5 ] );
        } catch ( Exception $e ) {
          // Ignore errors for this test
        }

        WP_CLI::success( 'Test completed' );
      });
      """
    And a wp-cli.yml file:
      """
      require:
        - http-methods-test.php
      """

    When I try `wp http-methods-test --debug=http`
    Then STDERR should contain:
      """
      Debug: HTTP GET request to https://httpbin.org/get
      """
    And STDERR should contain:
      """
      Debug: HTTP POST request to https://httpbin.org/post
      """
    And the return code should be 0
