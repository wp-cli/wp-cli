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
    Then it should run with errors
    And STDERR should be:
      """
      Error: A term with the name provided already exists.
      """

    When I run `wp term list post_tag --format=json`
    Then it should run without errors
    And STDOUT should be JSON containing:
      """
      [{"term_id":"2","term_taxonomy_id":"2","name":"Test term","slug":"test","description":"This is a test term","parent":"0","count":"0"}]
      """
