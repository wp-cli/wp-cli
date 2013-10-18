Feature: Perform database operations

  Scenario: DB CRUD
    Given an empty directory
    And WP files
    And wp-config.php

    When I run `wp db create`
    Then STDOUT should not be empty

    When I try the previous command again
    Then the return code should be 1

    When I run `wp db optimize`
    Then STDOUT should not be empty

    When I run `wp db repair`
    Then STDOUT should not be empty

  Scenario: DB Query
    Given a WP install

    When I run `wp db query 'SELECT COUNT(*) as total FROM wp_posts'`
    Then STDOUT should contain:
      """
      total
      """

    Given a debug.sql file:
      """
      SELECT COUNT(*) as total FROM wp_posts
      """
    When I run `wp db query < debug.sql`
    Then STDOUT should contain:
      """
      total
      """

  Scenario: DB export/import
    Given a WP install

    When I run `wp post list --format=count`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp db export /tmp/wp-cli-behat.sql`
    Then STDOUT should contain:
      """
      Success: Exported
      """

    When I run `wp db reset --yes`
    Then STDOUT should contain:
      """
      Success: Database reset.
      """

    When I try `wp post list --format=count`
    Then STDERR should not be empty

    When I run `wp db import /tmp/wp-cli-behat.sql`
    Then STDOUT should contain:
      """
      Success: Imported
      """

    When I run `wp post list --format=count`
    Then STDOUT should contain:
      """
      1
      """
