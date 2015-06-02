@require-php-5.4
Feature: Serve WordPress locally

  Scenario: Vanilla install
    Given a WP install
    And I launch in the background `wp server --host=localhost --port=8181`

    When I run `curl -sS localhost:8181`
    Then STDOUT should contain:
    """
    Just another WordPress site
    """

    When I run `curl -sS localhost:8181/license.txt > /tmp/license.txt`
    And I run `cmp /tmp/license.txt license.txt`
    Then STDOUT should be empty
