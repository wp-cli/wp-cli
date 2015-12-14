Feature: Generate comments

  Scenario: Generate a specific number of comments
    Given a WP install 

    When I run `wp comment generate --count=20`
    And I run `wp comment list --format=count`
    Then STDOUT should be:
      """
      21
      """
