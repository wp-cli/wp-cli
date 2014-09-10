Feature: Manage WordPress terms

  Background:
    Given a WP install

  Scenario: Creating/listing a term
    When I run `wp term create post_tag 'Test term' --slug=test --description='This is a test term' --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {TERM_ID}

    When I try the previous command again
    Then STDERR should not be empty

    When I run `wp term list post_tag --format=json`
    Then STDOUT should be JSON containing:
      """
      [{
        "name": "Test term",
        "slug":"test",
        "description":"This is a test term",
        "parent":"0",
        "count":"0"
      }]
      """

    When I run `wp term list post_tag --fields=name,slug --format=csv`
    Then STDOUT should be CSV containing:
      | name      | slug |
      | Test term | test |

    When I run `wp term create category 'Test category' --slug=test-category --description='This is a test category'`
    Then STDOUT should not be empty

    When I run `wp term list post_tag category --fields=name,slug --format=csv`
    Then STDOUT should be CSV containing:
      | name           | slug           |
      | Test term      | test           |
      | Test category  | test-category  |

    When I run `wp term get post_tag {TERM_ID}`
    Then STDOUT should be a table containing rows:
      | Field     | Value      |
      | term_id   | {TERM_ID}  |
      | name      | Test term  |

  Scenario: Creating/deleting a term
    When I run `wp term create post_tag 'Test delete term' --slug=test-delete --description='This is a test term to be deleted' --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {TERM_ID}

    When I run `wp term get post_tag {TERM_ID} --field=slug --format=json`
    Then STDOUT should contain:
      """
      "test-delete"
      """

    When I run `wp term delete post_tag {TERM_ID}`
    Then STDOUT should contain:
      """
      Deleted post_tag {TERM_ID}.
      """

    When I try the previous command again
    Then STDERR should not be empty

  Scenario: Generating terms
    When I run `wp term generate category --count=10`
    And I run `wp term list category --format=count`
    Then STDOUT should be:
      """
      11
      """

  Scenario: Term with a non-existent parent
    When I try `wp term create category Apple --parent=99 --porcelain`
    Then STDERR should be:
      """
      Error: Parent term does not exist.
      """
