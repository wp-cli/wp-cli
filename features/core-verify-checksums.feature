Feature: Validate checksums for WordPress install

  Scenario: Verify core checksums
    Given a WP install

    When I run `wp core update`
    Then STDOUT should not be empty

    When I run `wp core verify-checksums`
    Then STDOUT should be:
      """
      Success: WordPress install verifies against checksums.
      """

  Scenario: Core checksums don't verify
    Given a WP install
    And "WordPress" replaced with "Wordpress" in the readme.html file

    When I try `wp core verify-checksums`
    Then STDERR should be:
      """
      Warning: File doesn't verify against checksum: readme.html
      Error: WordPress install doesn't verify against checksums.
      """

    When I run `rm readme.html`
    Then STDERR should be empty

    When I try `wp core verify-checksums`
    Then STDERR should be:
      """
      Warning: File doesn't exist: readme.html
      Error: WordPress install doesn't verify against checksums.
      """

  Scenario: Verify core checksums without loading WordPress
    Given an empty directory
    And I run `wp core download --version=4.3`

    When I run `wp core verify-checksums`
    Then STDOUT should be:
      """
      Success: WordPress install verifies against checksums.
      """

    When I run `wp core verify-checksums --version=4.3 --locale=en_US`
    Then STDOUT should be:
      """
      Success: WordPress install verifies against checksums.
      """

    When I try `wp core verify-checksums --version=4.2 --locale=en_US`
    Then STDERR should contain:
      """
      Error: WordPress install doesn't verify against checksums.
      """

  Scenario: Verify core checksums for a non US local
    Given a WP install
    And I run `wp core download --locale=en_GB --version=4.3.1 --force`

    When I run `wp core verify-checksums`
    Then STDOUT should be:
      """
      Success: WordPress install verifies against checksums.
      """

  Scenario: Verify core checksums with extra files
    Given a WP install

    When I run `wp core update`
    Then STDOUT should not be empty

    Given a wp-includes/extra-file.txt file:
      """
      hello world
      """
    Then the wp-includes/extra-file.txt file should exist

    When I run `wp core verify-checksums`
    Then STDERR should be:
      """
      Warning: File should not exist: wp-includes/extra-file.txt
      """
    And STDOUT should be:
      """
      Success: WordPress install verifies against checksums.
      """

  Scenario: Verify core checksums with a plugin that has wp-admin
    Given a WP install
    And a wp-content/plugins/akismet/wp-admin/extra-file.txt file:
      """
      hello world
      """

    When I run `wp core verify-checksums`
    Then STDOUT should be:
      """
      Success: WordPress install verifies against checksums.
      """
    And STDERR should be empty
