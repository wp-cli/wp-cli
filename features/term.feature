Feature: Manage WordPress terms

  Scenario: Creating/listing a term
    Given a WP install

    When I run `wp term create 'Test term' post_tag --slug=test --description='This is a test term'`
    Then it should run without errors
    And STDOUT should be:
      """
      Success: Term created.
      """

    When I run the previous command again
    Then STDERR should not be empty

    When I run `wp term list post_tag --format=json`
    Then it should run without errors
    And STDOUT should be JSON containing:
      """
      [{"name":"Test term","slug":"test","description":"This is a test term","parent":"0","count":"0"}]
      """
