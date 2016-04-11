Feature: Manage WP-CLI packages

  Scenario: Package CRUD
    Given an empty directory

    When I run `wp package browse`
    Then STDOUT should contain:
      """
      danielbachhuber/wp-cli-reset-post-date-command
      """

    When I run `wp package install danielbachhuber/wp-cli-reset-post-date-command`
    Then STDERR should be empty

    When I run `wp help reset-post-date`
    Then STDERR should be empty

    When I try `wp --skip-packages --debug help reset-post-date`
    Then STDERR should contain:
      """
      Debug (bootstrap): Skipped loading packages.
      """
    And STDERR should contain:
      """
      Error: This does not seem to be a WordPress install.
      """

    When I run `wp package list`
    Then STDOUT should contain:
      """
      danielbachhuber/wp-cli-reset-post-date-command
      """

    When I run `wp package uninstall danielbachhuber/wp-cli-reset-post-date-command`
    Then STDERR should be empty

    When I run `wp package list`
    Then STDOUT should not contain:
      """
      danielbachhuber/wp-cli-reset-post-date-command
      """

  Scenario: Run package commands early, before any bad code can break them
    Given an empty directory
    And a bad-command.php file:
      """
      <?php
      WP_CLI::error( "Doing it wrong." );
      """

    When I try `wp --require=bad-command.php option`
    Then STDERR should contain:
      """
      Error: Doing it wrong.
      """

    When I run `wp --require=bad-command.php package list`
    Then STDERR should be empty
