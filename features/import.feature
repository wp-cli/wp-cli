Feature: Import content.

  Scenario: Basic export then import
    Given a WP install
    And I run `wp post generate --post_type=post --count=3`
    And I run `wp post generate --post_type=page --count=2`
    When I run `wp post list --post_type=any --format=count`
    Then STDOUT should be:
      """
      7
      """

    When I run `wp export`
    And save STDOUT 'Writing to file %s' as {EXPORT_FILE}

    When I run `wp site empty --yes`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=any --format=count`
    Then STDOUT should be:
      """
      0
      """

    When I run `wp plugin install wordpress-importer --activate`
    Then STDOUT should not be empty

    When I run `wp import {EXPORT_FILE} --authors=skip`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=any --format=count`
    Then STDOUT should be:
      """
      7
      """

    When I run `wp import {EXPORT_FILE} --authors=skip --skip=image_resize`
    Then STDOUT should not be empty
