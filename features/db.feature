Feature: Perform database operations

  Scenario: DB CRUD
    Given an empty directory
    And WP files
    And wp-config.php

    When I run `wp db create`
    Then it should run without errors
    And STDOUT should not be empty

    When I run the previous command again
    Then the return code should be 1

    When I run `wp db reset --yes`
    Then it should run without errors
    And STDOUT should not be empty

    When I run `wp db optimize`
    Then it should run without errors
    And STDOUT should not be empty

    When I run `wp db repair`
    Then it should run without errors
    And STDOUT should not be empty

    When I run `wp db query 'SELECT COUNT(*) FROM wp_posts'`
    Then it should run without errors
    And STDOUT should match '%d'
