Feature: Manage WordPress comments

  Background:
    Given a WP install

  Scenario: Creating/updating/deleting comments
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

    When I run `wp comment get {COMMENT_ID} --field=author`
    Then STDOUT should be:
      """
      Johnny
      """

    When I run `wp comment delete {COMMENT_ID}`
    Then STDOUT should be:
      """
	  Success: Deleted comment {COMMENT_ID}.
      """
      
    When I run `wp comment create --comment_post_ID=1`
    And I run `wp comment create --comment_post_ID=1`
    And I run `wp comment delete 3 4`
    Then STDOUT should be:
      """
      Success: Deleted comment 3.
      Success: Deleted comment 4.
      """
  
  Scenario: Get details about an existing comment
    When I run `wp comment get 1`
    Then STDOUT should be a table containing rows:
      | Field           | Value          |
      | comment_author  | Mr WordPress   |

    When I run `wp comment get 1 --fields=comment_author,comment_author_email --format=json`
    Then STDOUT should be:
      """
      {"comment_author":"Mr WordPress","comment_author_email":""}
      """

    When I run `wp comment list --fields=comment_approved,comment_author`
    Then STDOUT should be a table containing rows:
      | comment_approved | comment_author |
      | 1                | Mr WordPress   |

    When I run `wp comment list --field=approved`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp comment list --format=ids`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp comment url 1`
    Then STDOUT should be:
      """
      http://example.com/?p=1#comment-1
      """

  Scenario: Count  comments
    When I run `wp comment count 1`
    Then STDOUT should be:
      """
      approved:        1
      moderated:       0
      spam:            0
      trash:           0
      post-trashed:    0
      total_comments:  1
      """

    When I run `wp comment count`
    Then STDOUT should be:
      """
      approved:        1
      moderated:       0
      spam:            0
      trash:           0
      post-trashed:    0
      total_comments:  1
      """

  Scenario: Approving/unapproving comments
    Given I run `wp comment create --comment_post_ID=1 --comment_approved=0 --porcelain`
    And save STDOUT as {COMMENT_ID}

    When I run `wp comment approve {COMMENT_ID}`
    Then STDOUT should contain:
      """
      Approved comment {COMMENT_ID}
      """

    When I run `wp comment get --field=comment_approved {COMMENT_ID}`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp comment unapprove {COMMENT_ID}`
    Then STDOUT should contain:
      """
      Unapproved comment {COMMENT_ID}
      """

    When I run `wp comment get --field=comment_approved {COMMENT_ID}`
    Then STDOUT should be:
      """
      0
      """

