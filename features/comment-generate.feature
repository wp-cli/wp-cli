Feature: Generate comments

  Background:
    Given a WP install

  Scenario: Generate a specific number of comments
    When I run `wp comment generate --count=20`
    And I run `wp comment list --format=count`
    Then STDOUT should be:
      """
      21
      """

  Scenario: Generate comments assigned to a specific post
    When I run `wp comment generate --count=4 --post_id=2`
    And I run `wp comment list --post_id=2 --format=count`
    Then STDOUT should be:
      """
      4
      """

    When I run `wp comment list --post_id=1 --format=count`
    Then STDOUT should be:
      """
      1
      """

  Scenario: Generating comments and outputting ids
    When I run `wp comment generate --count=1 --format=ids`
    Then save STDOUT as {COMMENT_ID}

    When I run `wp comment update {COMMENT_ID} --comment_content="foo"`
    Then STDOUT should contain:
      """
      Success:
      """
