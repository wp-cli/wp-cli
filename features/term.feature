Feature: Manage WordPress terms

  Scenario: Creating/listing a term
    Given a WP install

    When I run `wp term create 'Test term' post_tag --slug=test --description='This is a test term' --porcelain`
    Then it should run without errors
    And STDOUT should match '%d'

    When I run the previous command again
    Then STDERR should not be empty

    When I run `wp term list post_tag --format=json`
    Then it should run without errors
    And STDOUT should be JSON containing:
      """
      [{"name":"Test term","slug":"test","description":"This is a test term","parent":"0","count":"0"}]
      """

    When I run `wp term list post_tag --fields=name,slug --format=csv`
    Then it should run without errors
    And STDOUT should be CSV containing:
      """
      name,slug
      "Test term",test
      """

  Scenario: Creating/deleting a term
    Given a WP install

    When I run `wp term create 'Test delete term' post_tag --slug=test-delete --description='This is a test term to be deleted' --porcelain`
    Then it should run without errors
    And STDOUT should match '%d'
    And save STDOUT as {TERM_ID}

    When I run `wp term delete {TERM_ID} post_tag`
    Then it should run without errors
    And STDOUT should contain:
      """
      Deleted post_tag {TERM_ID}.
      """

    When I run the previous command again
    Then STDERR should not be empty
