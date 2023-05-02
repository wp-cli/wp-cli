Feature: Requests integration with both v1 and v2

  Scenario: Composer stack with Requests v1
    Given an empty directory
    And a composer.json file:
      """
      {
          "name": "wp-cli/composer-test",
          "type": "project",
          "require": {
              "wp-cli/wp-cli": "2.7.0",
              "wp-cli/core-command": "^2",
              "wp-cli/eval-command": "^2"
          }
      }
      """
    # Note: Composer outputs messages to stderr.
    And I run `composer install --no-interaction 2>&1`

    When I run `vendor/bin/wp cli version`
    Then STDOUT should contain:
      """
      WP-CLI 2.7.0
      """

    Given a WP installation
    And I run `vendor/bin/wp core update --version=5.8 --force`

    When I run `vendor/bin/wp core version`
    Then STDOUT should contain:
      """
      5.8
      """

    When I run `vendor/bin/wp eval 'var_dump( \WP_CLI\Utils\http_request( "GET", "https://example.com/" ) );'`
    Then STDOUT should contain:
      """
      object(Requests_Response)
      """
    And STDOUT should contain:
      """
      HTTP/1.1 200 OK
      """
    And STDERR should be empty

  Scenario: Current version with WordPress-bundled Requests v1
    Given a WP installation
    And I run `wp core update --version=5.8 --force`

    When I run `wp core version`
    Then STDOUT should contain:
      """
      5.8
      """

    When I run `wp eval 'var_dump( \WP_CLI\Utils\http_request( "GET", "https://example.com/" ) );'`
    Then STDOUT should contain:
      """
      object(WpOrg\Requests\Response)
      """
    And STDOUT should contain:
      """
      HTTP/1.1 200 OK
      """
    And STDERR should be empty

    When I run `wp eval 'var_dump( \WpOrg\Requests\Requests::get( "https://example.com/" ) );'`
    Then STDOUT should contain:
      """
      object(WpOrg\Requests\Response)
      """
    And STDOUT should contain:
      """
      HTTP/1.1 200 OK
      """
    And STDERR should be empty

    When I run `wp eval 'var_dump( \Requests::get( "https://example.com/" ) );'`
    Then STDOUT should contain:
      """
      object(WpOrg\Requests\Response)
      """
    And STDOUT should contain:
      """
      HTTP/1.1 200 OK
      """
    And STDERR should be empty

  Scenario: Current version with WordPress-bundled Requests v2
    Given a WP installation
    And I run `wp core update --version=6.2 --force`

    When I run `wp core version`
    Then STDOUT should contain:
      """
      6.2
      """

    When I run `wp eval 'var_dump( \WP_CLI\Utils\http_request( "GET", "https://example.com/" ) );'`
    Then STDOUT should contain:
      """
      object(WpOrg\Requests\Response)
      """
    And STDOUT should contain:
      """
      HTTP/1.1 200 OK
      """
    And STDERR should be empty

    When I run `wp eval 'var_dump( \WpOrg\Requests\Requests::get( "https://example.com/" ) );'`
    Then STDOUT should contain:
      """
      object(WpOrg\Requests\Response)
      """
    And STDOUT should contain:
      """
      HTTP/1.1 200 OK
      """
    And STDERR should be empty

    # Expect a deprecation warning here.
    When I try `wp eval 'var_dump( \Requests::get( "https://example.com/" ) );'`
    Then STDOUT should contain:
      """
      object(WpOrg\Requests\Response)
      """
    And STDOUT should contain:
      """
      HTTP/1.1 200 OK
      """
    And STDERR should contain:
      """
      The PSR-0 `Requests_...` class names in the Requests library are deprecated.
      """
