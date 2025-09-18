Feature: YAML formatter outputs scalar values correctly

  Scenario: Output scalar zero with --format=yaml
    Given a WP install
    When I run `wp option update blog_public 0`
    And I run `wp option get blog_public --format=yaml`
    Then STDOUT should contain:
      """
      ---
      0
      """

  Scenario: Output scalar string with --format=yaml
    Given a WP install
    When I run `wp option add test_string hello`
    And I run `wp option get test_string --format=yaml`
    Then STDOUT should contain:
      """
      ---
      hello
      """

