Feature: Import content.

Scenario: Basic export then import
    Given a WP install

    When I run `wp post generate --post_type=post --count=3`
    Then STDOUT should not be empty

    When I run `wp post generate --post_type=page --count=2`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=any --format=csv | wc -l`
    Then STDOUT should be:
      """
      8
      """

    When I run `wp export`
    Then STDOUT should contain:
      """
      All done with export
      """
    And save STDOUT 'Writing to file %s' as {EXPORT_FILE}

    When I run `wp site empty --yes`
    Then STDOUT should not be empty

   	When I run `wp post list --post_type=any --format=csv | wc -l`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp plugin install wordpress-importer --activate`
    Then STDOUT should not be empty

    When I run `wp import {EXPORT_FILE} --authors=skip`
    Then STDOUT should not be empty

    When I run `wp post list --post_type=any --format=csv | wc -l`
    Then STDOUT should be:
      """
      8
      """