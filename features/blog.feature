Feature: Manage a WordPress installation

  Scenario: Install multisite
    Given a WP install

    When I run `wp core install-network`
    Then it should run without errors

    When I run the previous command again
    Then the return code should be 1

  Scenario: Delete a blog by id
    Given a WP multisite install

    When I run `wp blog create --slug=first --porcelain`
    Then it should run without errors
    And STDOUT should match '%d'
    And save STDOUT as {BLOG_ID}

    When I run `wp blog delete {BLOG_ID} --yes`
    Then it should run without errors
    And STDOUT should not be empty

    When I run the previous command again
    Then the return code should be 1

  Scenario: Delete a blog by slug
    Given a WP multisite install

    When I run `wp blog create --slug=first`
    Then it should run without errors
    And STDOUT should not be empty

    When I run `wp blog delete --slug=first --yes`
    Then it should run without errors
    And STDOUT should not be empty

    When I run the previous command again
    Then the return code should be 1

 Scenario: Empty a blog
    Given a WP install

    When I run `wp post create --post_title='Test post' --post_content='Test content.' --porcelain`
    Then it should run without errors
    And STDOUT should not be empty

    When I run `wp term create 'Test term' post_tag --slug=test --description='This is a test term'`
    Then it should run without errors
    And STDOUT should not be empty

    When I run `wp blog empty --yes`
    Then it should run without errors
    And STDOUT should not be empty

    When I run `wp post list --format=ids`
    Then it should run without errors
    And STDOUT should be empty

    When I run `wp term list post_tag --format=ids`
    Then it should run without errors
    And STDOUT should be empty