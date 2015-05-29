@require-php-5.4
Feature: Serve WordPress locally

  Scenario: Vanilla install
    Given a WP install
    And I start `wp server --host=localhost --port=8181`

    When I run `curl -sS localhost:8181`
    Then STDOUT should contain:
    """
    Just another WordPress site
    """

    When I run `test "$(curl -sS localhost:8181/license.txt)" == "$(cat license.txt)"`
