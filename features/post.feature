Feature: Manage WordPress posts

  Scenario: Creating/updating/deleting posts
    Given a WP install

    When I run `wp post create --post_title='Test post' --porcelain`
    Then it should run without errors
    And STDOUT should match '%d'
    And save STDOUT as {POST_ID}

    When I run `wp post update {POST_ID} --post_title='Updated post'`
    Then it should run without errors
    And STDOUT should be:
      """
      Success: Updated post {POST_ID}.
      """

    When I run `wp post delete {POST_ID}`
    Then it should run without errors
    And STDOUT should be:
      """
      Success: Trashed post {POST_ID}.
      """

    When I run the previous command again
    Then it should run without errors

    When I run the previous command again
    Then the return code should be 1

  Scenario: Creating/getting posts
    Given a WP install

    When I run `wp post create --post_title='Test post' --post_content='Test content.' --porcelain`
    Then it should run without errors
    And STDOUT should match '%d'
    And save STDOUT as {POST_ID}

    When I run `wp post get {POST_ID}`
    Then it should run without errors
    And STDOUT should be:
      """
      Test content.
      """

    When I run `wp post get --format=content {POST_ID}`
    Then it should run without errors
    And STDOUT should be:
      """
      Test content.
      """

    When I run `wp post get --format=table {POST_ID}`
    Then it should run without errors
    And STDOUT should be a table containing rows:
      """
      Field	Value
      ID	{POST_ID}
      post_title	Test post
      """

    When I run `wp post get --format=json {POST_ID}`
    Then it should run without errors
    And STDOUT should be JSON containing:
      """
      {"ID":{POST_ID},"post_title":"Test post","post_content":"Test content."}
      """
