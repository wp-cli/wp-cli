Feature: YAML formatter outputs scalar values correctly

  Scenario: Output scalar zero with --format=yaml
  Given a WP install
  When I run `wp option update blog_public 0`
  And I run `wp option get blog_public --format=yaml`
  Then STDOUT should be:
    """
    ---
    0
    """

  Scenario: Output string with --format=yaml
    Given a WP install
    When I run `wp option update test_string "hello"`
    And I run `wp option get test_string --format=yaml`
    Then STDOUT should be:
      """
      ---
      hello
      """

