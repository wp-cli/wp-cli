Feature: Generate WP users

  Background:
    Given a WP install

  Scenario: Generating users and outputting ids
    When I run `wp user generate --count=1 --format=ids`
    Then save STDOUT as {USER_ID}

    When I run `wp user update {USER_ID} --display_name="foo"`
    Then STDOUT should contain:
      """
      Success:
      """
