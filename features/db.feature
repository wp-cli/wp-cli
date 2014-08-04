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

  Scenario: DB export no charset
    Given a WP install
    And a replace-script.php file:
      """
      <?php
      $wp_config = file_get_contents( 'wp-config.php' );
      $wp_config = str_replace( 'utf8', '', $wp_config );
      file_put_contents( 'wp-config.php', $wp_config );
      WP_CLI::success( "Replaced charset" );
      """

    When I run `wp eval-file replace-script.php`
    Then STDOUT should not be empty

    When I run `wp db export /tmp/wp-cli-behat.sql`
    Then STDOUT should contain:
      """
      Success: Exported
      """

  Scenario: Persist DB charset and collation
    Given an empty directory
    And WP files

    When I run `wp core config {CORE_CONFIG_SETTINGS} --dbcharset=latin1 --dbcollate=latin1_spanish_ci`
    Then STDOUT should not be empty

    When I run `wp db create`
    Then STDERR should be empty

    When I run `wp core install --title="WP-CLI Test" --url=example.com --admin_user=admin --admin_password=admin --admin_email=admin@example.com`
    Then STDOUT should not be empty

    When I run `wp db query 'SHOW variables LIKE "character_set_database";'`
    Then STDOUT should contain:
      """
      latin1
      """

    When I run `wp db query 'SHOW variables LIKE "collation_database";'`
    Then STDOUT should contain:
      """
      latin1_spanish_ci
      """

    When I run `wp db reset --yes`
    Then STDOUT should not be empty

    When I run `wp core install --title="WP-CLI Test" --url=example.com --admin_user=admin --admin_password=admin --admin_email=admin@example.com`
    Then STDOUT should not be empty

    When I run `wp db query 'SHOW variables LIKE "character_set_database";'`
    Then STDOUT should contain:
      """
      latin1
      """

    When I run `wp db query 'SHOW variables LIKE "collation_database";'`
    Then STDOUT should contain:
      """
      latin1_spanish_ci
      """
