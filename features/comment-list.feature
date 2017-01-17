Feature: List WordPress comments

  @require-wp-4.1
  Scenario: Filter comments based on `comment__in` and `comment__not_in`
    Given a WP install

    When I run `wp comment create --comment_post_ID=1 --porcelain`
    Then save STDOUT as {COMMENT_ID}

    When I run `wp comment list --comment__in=1,{COMMENT_ID} --format=ids --orderby=comment_ID --order=ASC`
    Then STDOUT should be:
      """
      1 {COMMENT_ID}
      """

    When I run `wp comment list --comment__in=1 --format=ids --orderby=comment_ID --order=ASC`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp comment list --comment__not_in=1,{COMMENT_ID} --format=ids --orderby=comment_ID --order=ASC`
    Then STDOUT should be:
      """
      """

    When I run `wp comment list --comment__not_in=1 --format=ids --orderby=comment_ID --order=ASC`
    Then STDOUT should be:
      """
      {COMMENT_ID}
      """
