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

    When I run `sed -i.bak s/WordPress/Wordpress/g readme.html`
    Then STDERR should be empty

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

    When I try `wp core verify-checksums`
    Then STDERR should contain:
      """
      Error: wp-config.php not found.
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
