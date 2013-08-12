Feature: Manage WordPress comments

  Scenario: Creating/updating/deleting comments
    Given a WP install

    When I run `wp comment create --comment_post_ID=1 --comment_content='Hello' --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {COMMENT_ID}

    When I run `wp comment exists {COMMENT_ID}`
    Then STDOUT should be:
      """
	  Success: Comment with ID {COMMENT_ID} exists.
      """

    When I run `wp comment delete {COMMENT_ID}`
    Then STDOUT should be:
      """
	  Success: Deleted comment {COMMENT_ID}.
      """
