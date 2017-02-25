Feature: Recount comments on a post

  Scenario: Recount comments on a post
    Given a WP install

    When I run `wp comment create --comment_post_ID=1 --comment_approved=1 --porcelain`
    And I run `wp comment create --comment_post_ID=1 --comment_approved=1 --porcelain`
    And I run `wp post get 1 --field=comment_count`
    Then STDOUT should be:
      """
      3
      """

    When I run `wp eval 'global $wpdb; $wpdb->update( $wpdb->posts, array( "comment_count" => 1 ), array( "ID" => 1 ) );'`
    And I run `wp post get 1 --field=comment_count`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp comment recount 1`
    Then STDOUT should be:
      """
      Updated post 1 comment count to 3.
      """
