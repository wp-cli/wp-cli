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
    When I run `wp core download --locale=de_DE`
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
