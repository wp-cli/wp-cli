Feature: Export content.

  Scenario: Basic export
    Given a WP install

    When I run `wp export`
    Then STDOUT should contain:
      """
      All done with export.
      """

  Scenario: Export argument validator
    Given a WP install

    When I try `wp export --post_type=wp-cli-party`
    Then STDERR should contain:
      """
      Warning: The post type wp-cli-party does not exist.
      """

    When I try `wp export --author=invalid-author`
    Then STDERR should contain:
      """
      Warning: Could not find a matching author for invalid-author
      """

    When I try `wp export --start_date=invalid-date`
    Then STDERR should contain:
      """
      Warning: The start_date invalid-date is invalid.
      """

    When I try `wp export --end_date=invalid-date`
    Then STDERR should contain:
      """
      Warning: The end_date invalid-date is invalid.
      """

  Scenario: Export with post_type and post_status argument
    Given a WP install

    When I run `wp plugin install wordpress-importer --activate`
    Then STDERR should not contain:
      """
      Warning:
      """

    When I run `wp site empty --yes`
    And I run `wp post generate --post_type=page --post_status=draft --count=10`
    And I run `wp post list --post_type=page --post_status=draft --format=count`
    Then STDOUT should be:
      """
      10
      """

    When I run `wp export --post_type=page --post_status=draft`
    And save STDOUT 'Writing to file %s' as {EXPORT_FILE}

    When I run `wp site empty --yes`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=page --post_status=draft --format=count`
    Then STDOUT should be:
      """
      0
      """

    When I run `wp import {EXPORT_FILE} --authors=skip`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=page --post_status=draft --format=count`
    Then STDOUT should be:
      """
      10
      """

  Scenario: Export a comma-separated list of post types
    Given a WP install

    When I run `wp plugin install wordpress-importer --activate`
    Then STDOUT should contain:
      """
      Success:
      """

    When I run `wp site empty --yes`
    And I run `wp post generate --post_type=page --count=10`
    And I run `wp post generate --post_type=post --count=10`
    And I run `wp post generate --post_type=attachment --count=10`
    And I run `wp post list --post_type=page,post,attachment --format=count`
    Then STDOUT should be:
      """
      30
      """

    When I run `wp export --post_type=page,post`
    And save STDOUT 'Writing to file %s' as {EXPORT_FILE}

    When I run `wp site empty --yes`
    Then STDOUT should not be empty

    When I run `wp post list --format=count`
    Then STDOUT should be:
      """
      0
      """

    When I run `wp import {EXPORT_FILE} --authors=skip`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=page,post --format=count`
    Then STDOUT should be:
      """
      20
      """

    When I run `wp post list --post_type=page --format=count`
    Then STDOUT should be:
      """
      10
      """

    When I run `wp post list --post_type=post --format=count`
    Then STDOUT should be:
      """
      10
      """

  Scenario: Export only one post
    Given a WP install

    When I run `wp plugin install wordpress-importer --activate`
    Then STDOUT should contain:
      """
      Success:
      """

    When I run `wp post generate --count=10`
    And I run `wp post list --format=count`
    Then STDOUT should be:
      """
      11
      """

    When I run `wp post create --post_title='Test post' --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {POST_ID}

    When I run `wp comment generate --count=2 --post_id={POST_ID}`
    And I run `wp comment list --format=count`
    Then STDOUT should contain:
      """
      3
      """

    When I run `wp export --post__in={POST_ID}`
    And save STDOUT 'Writing to file %s' as {EXPORT_FILE}

    When I run `wp site empty --yes`
    Then STDOUT should not be empty

    When I run `wp import {EXPORT_FILE} --authors=skip`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=post --format=count`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp comment list --format=count`
    Then STDOUT should be:
      """
      2
      """

  Scenario: Export multiple posts, separated by spaces
    Given a WP install

    When I run `wp plugin install wordpress-importer --activate`
    Then STDOUT should contain:
      """
      Success:
      """

    When I run `wp post create --post_title='Test post' --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {POST_ID}

    When I run `wp post create --post_title='Test post 2' --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {POST_ID_TWO}

    When I run `wp export --post__in="{POST_ID} {POST_ID_TWO}"`
    And save STDOUT 'Writing to file %s' as {EXPORT_FILE}

    When I run `wp site empty --yes`
    Then STDOUT should not be empty

    When I run `wp import {EXPORT_FILE} --authors=skip`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=post --format=count`
    Then STDOUT should be:
      """
      2
      """

  Scenario: Export posts within a given date range
    Given a WP install

    When I run `wp plugin install wordpress-importer --activate`
    Then STDERR should not contain:
      """
      Warning:
      """

    When I run `wp site empty --yes`
    And I run `wp post generate --post_type=post --post_date=2013-08-01 --count=10`
    And I run `wp post generate --post_type=post --post_date=2013-08-02 --count=10`
    And I run `wp post generate --post_type=post --post_date=2013-08-03 --count=10`
    And I run `wp post list --post_type=post --format=count`
    Then STDOUT should be:
      """
      30
      """

    When I run `wp export --post_type=post --start_date=2013-08-02 --end_date=2013-08-02`
    And save STDOUT 'Writing to file %s' as {EXPORT_FILE}

    When I run `wp site empty --yes`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=post --format=count`
    Then STDOUT should be:
      """
      0
      """

    When I run `wp import {EXPORT_FILE} --authors=skip`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=post --format=count`
    Then STDOUT should be:
      """
      10
      """

  Scenario: Export posts from a given category
    Given a WP install

    When I run `wp plugin install wordpress-importer --activate`
    Then STDERR should not contain:
      """
      Warning:
      """

    When I run `wp term create category Apple --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {TERM_ID}

    When I run `wp site empty --yes`
    And I run `wp post generate --post_type=post --count=10`
    And I run `wp post list --post_type=post --format=count`
    Then STDOUT should be:
      """
      10
      """

    When I run `for id in $(wp post list --posts_per_page=5 --ids); do wp post term add $id category Apple; done`
    And I run `wp post list --post_type=post --cat={TERM_ID} --format=count`
    Then STDOUT should be:
      """
      5
      """

    When I run `wp export --post_type=post --category=apple`
    And save STDOUT 'Writing to file %s' as {EXPORT_FILE}
    Then the {EXPORT_FILE} file should contain:
      """
      <category domain="category" nicename="apple"><![CDATA[Apple]]></category>
      """

    When I run `wp site empty --yes`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=post --format=count`
    Then STDOUT should be:
      """
      0
      """

    When I run `wp import {EXPORT_FILE} --authors=skip`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=post --format=count`
    Then STDOUT should be:
      """
      5
      """

  Scenario: Export posts should include user information
    Given a WP install
    And I run `wp plugin install wordpress-importer --activate`
    And I run `wp user create user user@user.com --role=editor --display_name="Test User"`
    And I run `wp post generate --post_type=post --count=10 --post_author=user`

    When I run `wp export`
    And save STDOUT 'Writing to file %s' as {EXPORT_FILE}
    Then the {EXPORT_FILE} file should contain:
      """
      <wp:author_display_name><![CDATA[Test User]]></wp:author_display_name>
      """

    When I run `wp site empty --yes`
    And I run `wp user list --field=user_login | xargs -n 1 wp user delete --yes`
    Then STDOUT should not be empty

    When I run `wp import {EXPORT_FILE} --authors=create`
    Then STDOUT should not be empty

    When I run `wp user get user --field=display_name`
    Then STDOUT should be:
      """
      Test User
      """

  Scenario: Export posts from a given starting post ID
    Given a WP install

    When I run `wp plugin install wordpress-importer --activate`
    Then STDERR should not contain:
      """
      Warning:
      """

    When I run `wp site empty --yes`
    And I run `wp post generate --post_type=post --count=10`
    And I run `wp post list --post_type=post --format=count`
    Then STDOUT should be:
      """
      10
      """

    When I run `wp export --start_id=6`
    And save STDOUT 'Writing to file %s' as {EXPORT_FILE}

    When I run `wp site empty --yes`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=post --format=count`
    Then STDOUT should be:
      """
      0
      """

    When I run `wp import {EXPORT_FILE} --authors=skip`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=post --format=count`
    Then STDOUT should be:
      """
      5
      """

  Scenario: Exclude a specific post type from export
    Given a WP install
    And I run `wp post generate --post_type=post --count=10`
    And I run `wp plugin install wordpress-importer --activate`

    When I run `wp post list --post_type=any --format=count`
    Then STDOUT should be:
      """
      12
      """

    When I run `wp export --post_type__not_in=post`
    And save STDOUT 'Writing to file %s' as {EXPORT_FILE}

    When I run `wp site empty --yes`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=any --format=count`
    Then STDOUT should be:
      """
      0
      """

    When I run `wp import {EXPORT_FILE} --authors=skip`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=any --format=count`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp post generate --post_type=post --count=10`
    And I run `wp post list --post_type=any --format=count`
    Then STDOUT should be:
      """
      11
      """

    When I run `wp export --post_type__not_in=post,page`
    And save STDOUT 'Writing to file %s' as {EXPORT_FILE}

    When I run `wp site empty --yes`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=any --format=count`
    Then STDOUT should be:
      """
      0
      """

    When I run `wp import {EXPORT_FILE} --authors=skip`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=any --format=count`
    Then STDOUT should be:
      """
      0
      """

  Scenario: Export a site with a custom filename format
    Given a WP install

    When I run `wp export --filename_format='foo-bar.{date}.{n}.xml'`
    Then STDOUT should contain:
      """
      foo-bar.
      """
    And STDOUT should contain:
      """
      000.xml
      """

  Scenario: Export a site and skip the comments
    Given a WP install
    And I run `wp comment generate --post_id=1 --count=2`
    And I run `wp plugin install wordpress-importer --activate`

    When I run `wp comment list --format=count`
    Then STDOUT should contain:
      """
      3
      """

    When I run `wp export --skip_comments`
    And save STDOUT 'Writing to file %s' as {EXPORT_FILE}

    When I run `wp site empty --yes`
    Then STDOUT should not be empty

    When I run `wp post list --format=count`
    Then STDOUT should contain:
      """
      0
      """

    When I run `wp comment list --format=count`
    Then STDOUT should contain:
      """
      0
      """

    When I run `wp import {EXPORT_FILE} --authors=skip`
    Then STDOUT should not be empty

    When I run `wp post list --format=count`
    Then STDOUT should contain:
      """
      1
      """

    When I run `wp comment list --format=count`
    Then STDOUT should contain:
      """
      0
      """
