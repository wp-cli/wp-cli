Feature: Perform database operations

  Scenario: DB CRUD
    Given an empty directory
    And WP files
    And wp-config.php

    When I try `wp option get home`
    Then STDOUT should be empty
    And STDERR should be:
      """
      Error: Can’t select database. We were able to connect to the database server (which means your username and password is okay) but not able to select the `wp_cli_test` database.
      """

    When I run `wp db create`
    Then STDOUT should be:
      """
      Success: Database created.
      """

    When I try the previous command again
    Then the return code should be 1

  Scenario: DB Operations
    Given a WP install

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

    When I run `wp db query 'SELECT * FROM wp_options WHERE option_name="home"' --skip-column-names`
    Then STDOUT should not contain:
      """
      option_name
      """
    And STDOUT should contain:
      """
      home
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

    When I run `wp db export wp-cli-behat.sql --porcelain`
    Then STDOUT should be:
      """
      wp-cli-behat.sql
      """

    When I try `wp db export - --porcelain`
    Then STDERR should be:
      """
      Error: Porcelain is not allowed when output mode is STDOUT.
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
    Given an empty directory
    And WP files

    When I run `wp core config {CORE_CONFIG_SETTINGS} --dbcharset=""`
    Then STDOUT should not be empty

    When I run `cat wp-config.php`
    Then STDOUT should contain:
      """
      define( 'DB_CHARSET', '' );
      """

    When I run `wp db create`
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
