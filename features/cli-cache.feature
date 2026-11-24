Feature: CLI Cache

  Scenario: Remove all files from cache directory
    Given an empty cache

    When I run `wp core download --path={CACHE_DIR} --version=6.9 --force`
    And I run `wp core download --path={CACHE_DIR} --version=6.9 --force --locale=de_DE`
    Then the {SUITE_CACHE_DIR}/core directory should contain:
      """
      wordpress-6.9-de_DE.zip
      wordpress-6.9-en_US.zip
      """

    When I run `wp cli cache clear`
    Then STDOUT should be:
      """
      Success: Cache cleared.
      """
    And STDERR should be empty
    And the {SUITE_CACHE_DIR}/core directory should not contain:
      """
      wordpress-6.9-de_DE.zip
      """
    And the {SUITE_CACHE_DIR}/core directory should not contain:
      """
      wordpress-6.9-en_US.zip
      """

  Scenario: Using a null device disables the cache without throwing an error
    Given an empty directory
    And a env-var.php file:
      """
      <?php
      putenv( 'WP_CLI_CACHE_DIR=/dev/null' );
      """

    When I run `wp --require=env-var.php core download --path={RUN_DIR}/wp-core --version=6.9 --force`
    Then STDERR should be empty

  Scenario: Remove all but newest files from cache directory
    Given an empty cache
    And a file-a-12345.tmp cache file:
      """
      -empty-
      """
    And a file-a-23456.tmp cache file:
      """
      -empty-
      """
    And a file-b-12345.tmp cache file:
      """
      -empty-
      """
    And a file-b-23456.tmp cache file:
      """
      -empty-
      """
    And a file-b-01234.tmp cache file:
      """
      -empty-
      """
    And a file-c-12345.tmp cache file:
      """
      -empty-
      """

    When I run `wp cli cache prune`
    Then STDOUT should be:
      """
      Success: Cache pruned.
      """
    And the {SUITE_CACHE_DIR}/file-a-12345.tmp file should not exist
    And the {SUITE_CACHE_DIR}/file-a-23456.tmp file should exist
    And the {SUITE_CACHE_DIR}/file-b-12345.tmp file should not exist
    And the {SUITE_CACHE_DIR}/file-b-23456.tmp file should exist
    And the {SUITE_CACHE_DIR}/file-b-01234.tmp file should not exist
    And the {SUITE_CACHE_DIR}/file-c-12345.tmp file should exist

  Scenario: Prune plugin cache files with version numbers
    Given an empty cache
    And a plugin/jetpack-8.6.1.zip cache file:
      """
      -empty-
      """
    And a plugin/jetpack-8.7.1.zip cache file:
      """
      -empty-
      """
    And a plugin/jetpack-8.9.1.zip cache file:
      """
      -empty-
      """
    And a plugin/loginizer-1.4.4.zip cache file:
      """
      -empty-
      """
    And a plugin/loginizer-1.4.8.zip cache file:
      """
      -empty-
      """
    And a plugin/loginizer-1.6.0.zip cache file:
      """
      -empty-
      """

    When I run `wp cli cache prune`
    Then STDOUT should be:
      """
      Success: Cache pruned.
      """
    And the {SUITE_CACHE_DIR}/plugin directory should contain:
      """
      jetpack-8.9.1.zip
      """
    And the {SUITE_CACHE_DIR}/plugin directory should contain:
      """
      loginizer-1.6.0.zip
      """
    And the {SUITE_CACHE_DIR}/plugin directory should not contain:
      """
      jetpack-8.6.1.zip
      """
    And the {SUITE_CACHE_DIR}/plugin directory should not contain:
      """
      jetpack-8.7.1.zip
      """
    And the {SUITE_CACHE_DIR}/plugin directory should not contain:
      """
      loginizer-1.4.4.zip
      """
    And the {SUITE_CACHE_DIR}/plugin directory should not contain:
      """
      loginizer-1.4.8.zip
      """

  Scenario: Install a plugin from the cache when it was downloaded before
    Given a WP install
    And an empty cache

    When I run `wp plugin install debug-bar --version=1.1.6`
    Then STDOUT should not contain:
      """
      Using cached file
      """
    And the {SUITE_CACHE_DIR}/plugin/debug-bar-1.1.6.zip file should exist

    When I run `wp plugin delete debug-bar`
    And I run `wp plugin install debug-bar --version=1.1.6`
    Then STDOUT should contain:
      """
      Using cached file '{SUITE_CACHE_DIR}/plugin/debug-bar-1.1.6.zip'...
      """
    And STDOUT should contain:
      """
      Plugin installed successfully.
      """

  Scenario: Clean the cache on shutdown when the random check picks this run
    Given an empty directory
    And an empty cache
    And a file-a-12345.tmp cache file:
      """
      -empty-
      """
    And a pick-cache-clean.php file:
      """
      <?php
      // Seed mt_rand() so that the 1 in 51 check in WP_CLI::get_cache() schedules the cleanup.
      for ( $seed = 0; ; $seed++ ) {
        mt_srand( $seed );
        if ( 0 === mt_rand( 0, 50 ) ) {
          break;
        }
      }
      mt_srand( $seed );
      WP_CLI::get_cache();
      """

    When I run `WP_CLI_CACHE_MAX_SIZE=1 wp --require=pick-cache-clean.php cli version`
    Then the {SUITE_CACHE_DIR}/file-a-12345.tmp file should not exist

  Scenario Outline: Read the cache limits from the environment
    Given an empty directory
    And an empty cache
    And a show-cache-limits.php file:
      """
      <?php
      $cache  = WP_CLI::get_cache();
      $limits = Closure::bind(
        function () {
          return array(
            'ttl'      => $this->ttl,
            'max_size' => $this->max_size,
          );
        },
        $cache,
        get_class( $cache )
      );
      foreach ( $limits() as $name => $value ) {
        WP_CLI::log( $name . ': ' . $value );
      }
      """

    When I run `<env> wp --require=show-cache-limits.php cli version`
    Then STDOUT should contain:
      """
      ttl: <ttl>
      max_size: <max_size>
      """

    Examples:
      | env                                                    | ttl      | max_size  |
      |                                                        | 15552000 | 314572800 |
      | WP_CLI_CACHE_EXPIRY= WP_CLI_CACHE_MAX_SIZE=            | 15552000 | 314572800 |
      | WP_CLI_CACHE_EXPIRY=0 WP_CLI_CACHE_MAX_SIZE=0          | 0        | 0         |
      | WP_CLI_CACHE_EXPIRY=7200 WP_CLI_CACHE_MAX_SIZE=1048576 | 7200     | 1048576   |
      | WP_CLI_CACHE_EXPIRY=-1 WP_CLI_CACHE_MAX_SIZE=-1        | -1       | -1        |
      | WP_CLI_CACHE_EXPIRY=abc WP_CLI_CACHE_MAX_SIZE=abc      | 15552000 | 314572800 |

  Scenario Outline: A cache expiry of 0 keeps old files when the cache is cleaned
    Given an empty directory
    And an empty cache
    And a file-a-12345.tmp cache file:
      """
      -empty-
      """
    And a pick-cache-clean.php file:
      """
      <?php
      // Make the cached file older than the default expiry of 180 days.
      touch( WP_CLI\Utils\get_cache_dir() . '/file-a-12345.tmp', time() - 200 * 86400 );

      // Seed mt_rand() so that the 1 in 51 check in WP_CLI::get_cache() schedules the cleanup.
      for ( $seed = 0; ; $seed++ ) {
        mt_srand( $seed );
        if ( 0 === mt_rand( 0, 50 ) ) {
          break;
        }
      }
      mt_srand( $seed );
      WP_CLI::get_cache();
      """

    When I run `<env> wp --require=pick-cache-clean.php cli version`
    Then the {SUITE_CACHE_DIR}/file-a-12345.tmp file should <result>

    Examples:
      | env                   | result    |
      |                       | not exist |
      | WP_CLI_CACHE_EXPIRY=0 | exist     |
