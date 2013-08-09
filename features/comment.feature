Feature: Manage WordPress comments

  Scenario: Creating/updating/deleting comments
    Given a WP install

    When I run `wp comment create --comment_post_ID=1 --comment_content='Hello' --porcelain`
    Then STDOUT should match '%d'
    And save STDOUT as {COMMENT_ID}

    When I run `wp comment exists {POST_ID}`
    Then STDOUT should be:
      """
	  Success: Comment with ID {POST_ID} exists.
      """

    When I run `wp comment delete {POST_ID}`
    Then STDOUT should be:
      """
	  Success: Deleted comment {POST_ID}.
      """
