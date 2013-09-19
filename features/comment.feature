Feature: Manage WordPress comments

  Scenario: Creating/updating/deleting comments
    Given a WP install

    When I run `wp comment create --comment_post_ID=1 --comment_content='Hello' --comment_author='Billy' --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {COMMENT_ID}

    When I run `wp comment exists {COMMENT_ID}`
    Then STDOUT should be:
      """
	  Success: Comment with ID {COMMENT_ID} exists.
      """

    When I run `wp comment update {COMMENT_ID} --comment_author='Johnny'`
    Then STDOUT should not be empty

    When I run `wp comment get {COMMENT_ID}`
    Then STDOUT should be a table containing rows:
      | Field           | Value  |
      | comment_author  | Johnny |

    When I run `wp comment delete {COMMENT_ID}`
    Then STDOUT should be:
      """
	  Success: Deleted comment {COMMENT_ID}.
      """
  
  Scenario: Get details about an existing comment
    Given a WP install

    When I run `wp comment get 1`
    Then STDOUT should be a table containing rows:
      | Field           | Value          |
      | comment_author  | Mr WordPress   |

    When I run `wp comment list --fields=comment_approved,comment_author`
    Then STDOUT should be a table containing rows:
      | comment_approved | comment_author |
      | 1                | Mr WordPress   |
