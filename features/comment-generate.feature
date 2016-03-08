Feature: Generate comments

  Scenario: Generate a specific number of comments
    Given a WP install 

    When I run `wp comment generate --count=20`
    And I run `wp comment list --format=count`
    Then STDOUT should be:
      """
      21
      """

  Scenario: Generate comments assigned to a specific post
    Given a WP install

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
