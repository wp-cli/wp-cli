Feature: List database tables

  Scenario: List database tables on a single WordPress install
    Given a WP install

    When I run `wp db tables`
    Then STDOUT should contain:
      """
      wp_users
      wp_usermeta
      wp_posts
      wp_comments
      wp_links
      wp_options
      wp_postmeta
      wp_terms
      wp_term_taxonomy
      wp_term_relationships
      """

    When I run `wp db tables --format=csv`
    Then STDOUT should contain:
      """
      wp_users,wp_usermeta,wp_posts,wp_comments,
      """

    When I run `wp db tables 'wp_post*' --format=csv`
    Then STDOUT should be:
      """
      wp_postmeta,wp_posts
      """

  @require-wp-3.9
  Scenario: List database tables on a multisite WordPress install
    Given a WP multisite install

    When I run `wp db tables`
    Then STDOUT should contain:
      """
      wp_users
      wp_usermeta
      wp_posts
      wp_comments
      wp_links
      wp_options
      wp_postmeta
      wp_terms
      wp_term_taxonomy
      wp_term_relationships
      """
    And STDOUT should contain:
      """
      wp_blogs
      wp_signups
      wp_site
      wp_sitemeta
      wp_sitecategories
      wp_registration_log
      wp_blog_versions
      """

    When I run `wp site create --slug=foo`
    And I run `wp db tables --url=example.com/foo`
    Then STDOUT should contain:
      """
      wp_users
      wp_usermeta
      wp_2_posts
      """

    When I run `wp db tables --url=example.com/foo --scope=global`
    Then STDOUT should not contain:
      """
      wp_2_posts
      """

    When I run `wp db tables --all-tables-with-prefix`
    Then STDOUT should contain:
      """
      wp_2_posts
      """
    And STDOUT should contain:
      """
      wp_posts
      """

    When I run `wp db tables --url=example.com/foo --all-tables-with-prefix`
    Then STDOUT should contain:
      """
      wp_2_posts
      """
    And STDOUT should not contain:
      """
      wp_posts
      """

    When I run `wp db tables --url=example.com/foo --network`
    Then STDOUT should contain:
      """
      wp_2_posts
      """
    And STDOUT should contain:
      """
      wp_posts
      """
