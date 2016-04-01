Feature: Generate WP users

  Background:
    Given a WP install

  Scenario: Generating and deleting users
    When I run `wp user list --role=editor --format=count`
    Then STDOUT should be:
      """
      0
      """

    When I run `wp user generate --count=10 --role=editor`
    And I run `wp user list --role=editor --format=count`
    Then STDOUT should be:
      """
      10
      """

    When I try `wp user list --field=ID | xargs wp user delete invalid-user --yes`
    And I run `wp user list --format=count`
    Then STDOUT should be:
      """
      0
      """

  Scenario: Generating users and outputting ids
    When I run `wp user generate --count=1 --format=ids`
    Then save STDOUT as {USER_ID}

    When I run `wp user update {USER_ID} --display_name="foo"`
    Then STDOUT should contain:
      """
      Success:
      """
