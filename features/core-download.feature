Feature: Download WordPress

  Scenario: Empty dir
    Given an empty directory
    And an empty cache

    When I try `wp core is-installed`
    Then the return code should be 1
    And STDERR should not be empty

    When I run `wp core download`
    And save STDOUT 'Downloading WordPress ([\d\.]+)' as {VERSION}
    Then the wp-settings.php file should exist
    And the {SUITE_CACHE_DIR}/core/wordpress-{VERSION}-en_US.tar.gz file should exist

    When I run `mkdir inner`
    And I run `cd inner && wp core download`
    Then the inner/wp-settings.php file should exist

    # test core tarball cache
    When I run `wp core download --force`
    Then the wp-settings.php file should exist
    And STDOUT should contain:
    """
    Using cached file '{SUITE_CACHE_DIR}/core/wordpress-{VERSION}-en_US.tar.gz'...
    """

  Scenario: Localized install
    Given an empty directory
    And an empty cache
    When I run `wp core download --version=4.4.2 --locale=de_DE`
    And save STDOUT 'Downloading WordPress ([\d\.]+)' as {VERSION}
    Then the wp-settings.php file should exist
    And the {SUITE_CACHE_DIR}/core/wordpress-{VERSION}-de_DE.tar.gz file should exist

  Scenario: Catch download of non-existent WP version
    Given an empty directory

    When I try `wp core download --version=4.1.0 --force`
    Then STDERR should contain:
      """
      Error: Release not found.
      """

  Scenario: Verify release hash when downloading new version
    Given an empty directory

    When I run `wp core download --version=4.4.1`
    Then STDOUT should contain:
      """
      md5 hash verified: 1907d1dbdac7a009d89224a516496b8d
      Success: WordPress downloaded.
      """

  Scenario: Core download to a directory specified by `--path` in custom command
    Given a WP install
    And a download-command.php file:
      """
      <?php
      class Download_Command extends WP_CLI_Command {
          public function __invoke() {
              WP_CLI::run_command( array( 'core', 'download' ), array( 'path' => 'src/' ) );
          }
      }
      WP_CLI::add_command( 'custom-download', 'Download_Command' );
      """

    When I run `wp --require=download-command.php custom-download`
    Then STDOUT should not be empty
    And the src directory should contain:
      """
      wp-load.php
      """

    When I try `wp --require=download-command.php custom-download`
    Then STDERR should be:
      """
      Error: WordPress files seem to already be present here.
      """

  Scenario: Make sure files are cleaned up
    Given an empty directory
    When I run `wp core download --version=4.4`
    Then the wp-includes/rest-api.php file should exist
    Then the wp-includes/class-wp-comment.php file should exist
    And STDERR should not contain:
      """
      Warning: Failed to find WordPress version. Please cleanup files manually.
      """

    When I run `wp core download --version=4.3.2 --force`
    Then the wp-includes/rest-api.php file should not exist
    Then the wp-includes/class-wp-comment.php file should not exist
    And STDOUT should not contain:
      """
      File removed: wp-content
      """

  Scenario: Installing nightly
    Given an empty directory
    And an empty cache

    When I run `wp core download --version=nightly`
    Then the wp-settings.php file should exist
    And the {SUITE_CACHE_DIR}/core/wordpress-nightly-en_US.zip file should not exist
    And STDOUT should contain:
      """
      Downloading WordPress nightly (en_US)...
      """
    And STDERR should contain:
      """
      Warning: md5 hash checks are not available for nightly downloads.
      """
    And STDOUT should contain:
      """
      Success: WordPress downloaded.
      """

	# we shouldn't cache nightly builds
    When I run `wp core download --version=nightly --force`
    Then the wp-settings.php file should exist
    And STDOUT should not contain:
    """
    Using cached file '{SUITE_CACHE_DIR}/core/wordpress-nightly-en_US.zip'...
    """

  Scenario: Installing nightly over an existing install
    Given an empty directory
    And an empty cache
    When I run `wp core download --version=4.5.3`
    Then the wp-settings.php file should exist
    When I run `wp core download --version=nightly --force`
    Then STDERR should not contain:
      """
      Warning: Failed to find WordPress version. Please cleanup files manually.
      """
    And STDERR should contain:
      """
      Warning: Failed to fetch checksums. Please cleanup files manually.
      """
    And STDOUT should contain:
      """
      Success: WordPress downloaded.
      """

  Scenario: Installing a version over nightly
    Given an empty directory
    And an empty cache
    When I run `wp core download --version=nightly`
    Then the wp-settings.php file should exist
    And STDERR should not contain:
      """
      Warning: Failed to find WordPress version. Please cleanup files manually.
      """

    When I run `wp core download --version=4.3.2 --force`
    Then the wp-includes/rest-api.php file should not exist
    And the wp-includes/class-wp-comment.php file should not exist
    And STDOUT should not contain:
      """
      File removed: wp-content
      """

  Scenario: Trunk is an alias for nightly
    Given an empty directory
    And an empty cache
    When I run `wp core download --version=trunk`
    Then the wp-settings.php file should exist
    And STDOUT should contain:
      """
      Downloading WordPress nightly (en_US)...
      """
    And STDERR should contain:
      """
      Warning: md5 hash checks are not available for nightly downloads.
      """
    And STDOUT should contain:
      """
      Success: WordPress downloaded.
      """

  Scenario: Installing nightly for a non-default locale
    Given an empty directory
    And an empty cache

    When I try `wp core download --version=nightly --locale=de_DE`
		Then the return code should be 1
    And STDERR should contain:
    """
    Error: Nightly builds are only available for the en_US locale.
    """

  Scenario: Installing a release candidate or beta version
    Given an empty directory
    And an empty cache

    # Test with incorrect case.
    When I try `wp core download --version=4.6-rc2`
    Then the return code should be 1
    Then STDERR should contain:
      """
      Error: Release not found.
      """

    When I run `wp core download --version=4.6-RC2`
    Then the wp-settings.php file should exist
    And STDOUT should contain:
      """
      Downloading WordPress 4.6-RC2 (en_US)...
      md5 hash verified: 90c93a15092b2d5d4c960ec1fc183e07
      Success: WordPress downloaded.
      """

  Scenario: Using --version=latest should produce a cache key of the version number, not 'latest'
    Given an empty directory
    And an empty cache

    When I run `wp core download --version=latest`
    Then STDOUT should contain:
      """
      Success: WordPress downloaded.
      """

    When I run `wp core version`
    Then save STDOUT as {VERSION}
    And the {SUITE_CACHE_DIR}/core/wordpress-latest-en_US.tar.gz file should not exist
    And the {SUITE_CACHE_DIR}/core/wordpress-{VERSION}-en_US.tar.gz file should exist
