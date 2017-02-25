Feature: Empty a WordPress site of its data

  Scenario: Empty a site
    Given a WP install
    And I run `wp option update uploads_use_yearmonth_folders 0`
    And download:
      | path                        | url                                              |
      | {CACHE_DIR}/large-image.jpg | http://wp-cli.org/behat-data/large-image.jpg     |

    When I run `wp media import {CACHE_DIR}/large-image.jpg --post_id=1`
    Then the wp-content/uploads/large-image.jpg file should exist

    When I try `wp site url 1`
    Then STDERR should be:
      """
      Error: This is not a multisite install.
      """

    When I run `wp post create --post_title='Test post' --post_content='Test content.' --porcelain`
    Then STDOUT should be:
      """
      4
      """

    When I run `wp term create post_tag 'Test term' --slug=test --description='This is a test term'`
    Then STDOUT should be:
      """
      Success: Created post_tag 2.
      """

    When I run `wp site empty --yes`
    Then STDOUT should be:
      """
      Success: The site at 'http://example.com' was emptied.
      """
    And the wp-content/uploads/large-image.jpg file should exist

    When I run `wp post list --format=ids`
    Then STDOUT should be empty

    When I run `wp term list post_tag --format=ids`
    Then STDOUT should be empty

  Scenario: Empty a site and its uploads directory
    Given a WP multisite install
    And I run `wp site create --slug=foo`
    And I run `wp --url=example.com/foo option update uploads_use_yearmonth_folders 0`
    And download:
      | path                        | url                                              |
      | {CACHE_DIR}/large-image.jpg | http://wp-cli.org/behat-data/large-image.jpg     |

    When I run `wp --url=example.com/foo media import {CACHE_DIR}/large-image.jpg --post_id=1`
    Then the wp-content/uploads/sites/2/large-image.jpg file should exist

    When I run `wp site empty --uploads --yes`
    Then STDOUT should not be empty
    And the wp-content/uploads/sites/2/large-image.jpg file should exist

    When I run `wp post list --format=ids`
    Then STDOUT should be empty

    When I run `wp --url=example.com/foo site empty --uploads --yes`
    Then STDOUT should be:
      """
      Success: The site at 'http://example.com/foo' was emptied.
      """
    And the wp-content/uploads/sites/2/large-image.jpg file should not exist

    When I run `wp --url=example.com/foo post list --format=ids`
    Then STDOUT should be empty
