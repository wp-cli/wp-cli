Feature: Do global search/replace

  Scenario: Basic search/replace
    Given a WP install

    When I run `wp search-replace foo bar`
    Then STDOUT should not be empty
