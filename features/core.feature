Feature: Manage WordPress installation

  Scenario: Empty dir
    Given an empty directory
    When I run `wp core is-installed`
    Then the return code should be 1

    When I run `wp core download --quiet`
    Then it should run without errors
    And the wp-settings.php file should exist

  Scenario: No wp-config.php
    Given an empty directory
    And WP files

    When I run `wp core is-installed`
    Then the return code should be 1

    When I run `wp core install`
    Then STDERR should be:
      """
      Error: wp-config.php not found.
      Either create one manually or use `wp core config`.
      """
    
    When I run `wp core config`
    Then it should run without errors
    And the wp-config.php file should exist

  Scenario: Database doesn't exist
    Given an empty directory
    And WP files
    And wp-config.php

    When I run `wp`
    Then the return code should be 1
    And STDERR should not be empty

    When I run `wp db create`
    Then it should run without errors

  Scenario: Database tables not installed
    Given an empty directory
    And WP files
    And wp-config.php
    And a database

    When I run `wp core is-installed`
    Then the return code should be 1

    When I run `wp`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: The site you have requested is not installed.
      Run `wp core install`.
      """

    When I run `wp core install`
    Then the return code should be 0

    When I run `wp core version`
    Then it should run without errors
    And STDOUT should not be empty

  Scenario: Full install
    Given a WP install

    When I run `wp core is-installed`
    Then it should run without errors

    When I run `wp eval 'var_export( is_admin() );'`
    Then it should run without errors
    And STDOUT should be:
      """
      true
      """ 

    When I run `wp eval 'var_export( function_exists( 'media_handle_upload' ) );'`
    Then it should run without errors
    And STDOUT should be:
      """
      true
      """ 

  Scenario: Custom wp-content directory
    Given a WP install
    And a custom wp-content directory

    When I run `wp plugin status hello`
    Then it should run without errors
