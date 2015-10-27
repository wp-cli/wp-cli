Feature: Manage WordPress installation

  @download
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

  @download
  Scenario: Localized install
    Given an empty directory
    And an empty cache
    When I run `wp core download --locale=de_DE`
    And save STDOUT 'Downloading WordPress ([\d\.]+)' as {VERSION}
    Then the wp-settings.php file should exist
    And the {SUITE_CACHE_DIR}/core/wordpress-{VERSION}-de_DE.tar.gz file should exist

  Scenario: No wp-config.php
    Given an empty directory
    And WP files

    When I try `wp core is-installed`
    Then the return code should be 1
    And STDERR should not be empty

    When I run `wp core version`
    Then STDOUT should not be empty

    When I try `wp core install`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: wp-config.php not found.
      Either create one manually or use `wp core config`.
      """

    Given a wp-config-extra.php file:
      """
      define( 'WP_DEBUG_LOG', true );
      """
    When I run `wp core config {CORE_CONFIG_SETTINGS} --extra-php < wp-config-extra.php`
    Then the wp-config.php file should contain:
      """
      define('AUTH_SALT',
      """
    And the wp-config.php file should contain:
      """
      define( 'WP_DEBUG_LOG', true );
      """
    And the wp-config.php file should not contain:
      """
      define( 'WPLANG', '' );
      """

    When I try the previous command again
    Then the return code should be 1
    And STDERR should not be empty

  Scenario: Configure with existing salts
    Given an empty directory
    And WP files

    When I run `wp core config {CORE_CONFIG_SETTINGS} --skip-salts --extra-php < /dev/null`
    Then the wp-config.php file should not contain:
      """
      define('AUTH_SALT',
      """

  Scenario: Define WPLANG when running WP < 4.0
    Given an empty directory
    And I run `wp core download --version=3.9 --force`

    When I run `wp core config {CORE_CONFIG_SETTINGS}`
    Then the wp-config.php file should contain:
      """
      define('WPLANG', '');
      """

  Scenario: Database doesn't exist
    Given an empty directory
    And WP files
    And wp-config.php

    When I try `wp core is-installed`
    Then the return code should be 1
    And STDERR should not be empty

    When I run `wp db create`
    Then STDOUT should not be empty

  Scenario: Database tables not installed
    Given an empty directory
    And WP files
    And wp-config.php
    And a database

    When I try `wp core is-installed`
    Then the return code should be 1

    When I try `wp core is-installed --network`
    Then the return code should be 1

    When I try `wp core install`
    Then the return code should be 1
    And STDERR should contain:
      """
      missing --url parameter (The address of the new site.)
      """

    When I run `wp core install --url='localhost:8001' --title='Test' --admin_user=wpcli --admin_email=admin@example.com --admin_password=1`
    Then STDOUT should not be empty

    When I run `wp eval 'echo home_url();'`
    Then STDOUT should be:
      """
      http://localhost:8001
      """

    When I try `wp core is-installed --network`
    Then the return code should be 1

  Scenario: Install WordPress by prompting
    Given an empty directory
    And WP files
    And wp-config.php
    And a database
    And a session file:
    """
    localhost:8001
    Test
    wpcli
    wpcli
    admin@example.com
    """

    When I run `wp core install --prompt < session`
    Then STDOUT should not be empty

    When I run `wp eval 'echo home_url();'`
    Then STDOUT should be:
      """
      http://localhost:8001
      """

  Scenario: Install WordPress with an https scheme
    Given an empty directory
    And WP files
    And wp-config.php
    And a database

    When I run `wp core install --url='https://localhost' --title='Test' --admin_user=wpcli --admin_email=admin@example.com --admin_password=1`
    Then the return code should be 0

    When I run `wp eval 'echo home_url();'`
    Then STDOUT should be:
      """
      https://localhost
      """

  Scenario: Install WordPress with an https scheme and non-standard port
    Given an empty directory
    And WP files
    And wp-config.php
    And a database

    When I run `wp core install --url='https://localhost:8443' --title='Test' --admin_user=wpcli --admin_email=admin@example.com --admin_password=1`
    Then the return code should be 0

    When I run `wp eval 'echo home_url();'`
    Then STDOUT should be:
      """
      https://localhost:8443
      """

  Scenario: Full install
    Given a WP install

    When I run `wp core is-installed`
    Then STDOUT should be empty
    And the wp-content/uploads directory should exist

    When I run `wp eval 'var_export( is_admin() );'`
    Then STDOUT should be:
      """
      false
      """

    When I run `wp eval 'var_export( function_exists( 'media_handle_upload' ) );'`
    Then STDOUT should be:
      """
      true
      """

    # Can complain that it's already installed, but don't exit with an error code
    When I try `wp core install --url='localhost:8001' --title='Test' --admin_user=wpcli --admin_email=admin@example.com --admin_password=1`
    Then the return code should be 0

  Scenario: Convert install to multisite
    Given a WP install

    When I run `wp eval 'var_export( is_multisite() );'`
    Then STDOUT should be:
      """
      false
      """

    When I try `wp core is-installed --network`
    Then the return code should be 1

    When I run `wp core install-network --title='test network'`
    Then STDOUT should not be empty

    When I run `wp eval 'var_export( is_multisite() );'`
    Then STDOUT should be:
      """
      true
      """

    When I run `wp core is-installed --network`
    Then the return code should be 0

    When I try `wp core install-network --title='test network'`
    Then the return code should be 1

  Scenario: Install multisite from scratch
    Given an empty directory
    And WP files
    And wp-config.php
    And a database

    When I run `wp core multisite-install --url=foobar.org --title=Test --admin_user=wpcli --admin_email=admin@example.com --admin_password=1`
    Then STDOUT should not be empty

    When I run `wp eval 'echo $GLOBALS["current_site"]->domain;'`
    Then STDOUT should be:
      """
      foobar.org
      """

    # Can complain that it's already installed, but don't exit with an error code
    When I try `wp core multisite-install --url=foobar.org --title=Test --admin_user=wpcli --admin_email=admin@example.com --admin_password=1`
    Then the return code should be 0

  Scenario: Install multisite from scratch, with MULTISITE already set in wp-config.php
    Given a WP multisite install
    And I run `wp db reset --yes`

    When I try `wp core is-installed`
    Then the return code should be 1

    When I run `wp core multisite-install --title=Test --admin_user=wpcli --admin_email=admin@example.com --admin_password=1`
    Then STDOUT should not be empty

    When I run `wp eval 'echo $GLOBALS["current_site"]->domain;'`
    Then STDOUT should be:
      """
      example.com
      """

  Scenario: Install multisite with subdomains on localhost
    Given an empty directory
    And WP files
    And wp-config.php
    And a database

    When I try `wp core multisite-install --url=http://localhost/ --title=Test --admin_user=wpcli --admin_email=admin@example.com --admin_password=1 --subdomains`
    Then STDERR should contain:
      """
      Error: Multisite with subdomains cannot be configured when domain is 'localhost'.
      """

  Scenario: Update from a ZIP file
    Given a WP install

    When I run `wp core download --version=3.8 --force`
    Then STDOUT should not be empty

    When I run `wp eval 'echo $GLOBALS["wp_version"];'`
    Then STDOUT should be:
      """
      3.8
      """

    When I run `wget http://wordpress.org/wordpress-3.9.zip --quiet`
    And I run `wp core update wordpress-3.9.zip`
    Then STDOUT should be:
      """
      Starting update...
      Unpacking the update...
      Success: WordPress updated successfully.
      """

    When I run `wp eval 'echo $GLOBALS["wp_version"];'`
    Then STDOUT should be:
      """
      3.9
      """

  @download @update-check
  Scenario: Check for update via Version Check API
    Given a WP install

    When I run `wp core download --version=3.8 --force`
    Then STDOUT should not be empty

    When I run `wp core check-update`
    Then STDOUT should be a table containing rows:
      | version | update_type | package_url                                |
      | 4.3.1   | major       | https://wordpress.org/wordpress-4.3.1.zip  |
      | 4.2.5   | major       | https://wordpress.org/wordpress-4.2.5.zip  |
      | 4.1.8   | major       | https://wordpress.org/wordpress-4.1.8.zip  |
      | 4.0.8   | major       | https://wordpress.org/wordpress-4.0.8.zip  |
      | 3.9.9   | major       | https://wordpress.org/wordpress-3.9.9.zip  |
      | 3.8.11  | minor       | https://wordpress.org/wordpress-3.8.11.zip |

    When I run `wp core check-update --format=count`
    Then STDOUT should be:
      """
      6
      """

    When I run `wp core check-update --major`
    Then STDOUT should be a table containing rows:
      | version | update_type | package_url                                |
      | 4.3.1   | major       | https://wordpress.org/wordpress-4.3.1.zip  |
      | 4.2.5   | major       | https://wordpress.org/wordpress-4.2.5.zip  |
      | 4.1.8   | major       | https://wordpress.org/wordpress-4.1.8.zip  |
      | 4.0.8   | major       | https://wordpress.org/wordpress-4.0.8.zip  |
      | 3.9.9   | major       | https://wordpress.org/wordpress-3.9.9.zip  |

    When I run `wp core check-update --major --format=count`
    Then STDOUT should be:
      """
      5
      """

    When I run `wp core check-update --minor`
    Then STDOUT should be a table containing rows:
      | version | update_type | package_url                                |
      | 3.8.11  | minor       | https://wordpress.org/wordpress-3.8.11.zip |

    When I run `wp core check-update --minor --format=count`
    Then STDOUT should be:
      """
      1
      """

  Scenario: Custom wp-content directory
    Given a WP install
    And a custom wp-content directory

    When I run `wp plugin status hello`
    Then STDOUT should not be empty

  Scenario: Core update from cache
    Given a WP install
    And an empty cache

    When I run `wp core update --version=3.8.1 --force`
    Then STDOUT should not contain:
      """
      Using cached file
      """
    And STDOUT should contain:
      """
      Downloading
      """

    When I run `wp core update --version=3.9 --force`
    Then STDOUT should not be empty

    When I run `wp core update --version=3.8.1 --force`
    Then STDOUT should contain:
      """
      Using cached file '{SUITE_CACHE_DIR}/core/wordpress-3.8.1-en_US.zip'...
      """
    And STDOUT should not contain:
      """
      Downloading
	  """

  Scenario: Don't run update when up-to-date
    Given a WP install
    And I run `wp core update`

    When I run `wp core update`
    Then STDOUT should contain:
      """
      WordPress is up to date
      """
    And STDOUT should not contain:
      """
      Updating
      """

    When I run `wp core update --force`
    Then STDOUT should contain:
      """
      Updating
      """

  Scenario: User defined in wp-cli.yml
    Given an empty directory
    And WP files
    And wp-config.php
    And a database
    And a wp-cli.yml file:
      """
      user: wpcli
      """

    When I run `wp core install --url='localhost:8001' --title='Test' --admin_user=wpcli --admin_email=admin@example.com --admin_password=1`
    Then STDOUT should not be empty

    When I run `wp eval 'echo home_url();'`
    Then STDOUT should be:
      """
      http://localhost:8001
      """

  Scenario: Catch download of non-existent WP version
    Given an empty directory

    When I try `wp core download --version=4.1.0 --force`
    Then STDERR should contain:
      """
      Error: Release not found.
      """

  Scenario: Test output in a multisite install with custom base path
    Given a WP install

    When I run `wp core multisite-convert --title=Test --base=/test/`
    And I run `wp post list`
    Then STDOUT should contain:
      """
      Hello world!
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

  Scenario: Ensure cached partial upgrades aren't used in full upgrade
    Given a WP install
    And an empty cache
    And a wp-content/mu-plugins/upgrade-override.php file:
      """
      <?php
      add_filter( 'pre_site_transient_update_core', function(){
        return (object) array(
          'updates' => array(
              (object) array(
                'response' => 'autoupdate',
                'download' => 'https://downloads.wordpress.org/release/wordpress-4.2.4.zip',
                'locale' => 'en_US',
                'packages' => (object) array(
                  'full' => 'https://downloads.wordpress.org/release/wordpress-4.2.4.zip',
                  'no_content' => 'https://downloads.wordpress.org/release/wordpress-4.2.4-no-content.zip',
                  'new_bundled' => 'https://downloads.wordpress.org/release/wordpress-4.2.4-new-bundled.zip',
                  'partial' => 'https://downloads.wordpress.org/release/wordpress-4.2.4-partial-1.zip',
                  'rollback' => 'https://downloads.wordpress.org/release/wordpress-4.2.4-rollback-1.zip',
                ),
                'current' => '4.2.4',
                'version' => '4.2.4',
                'php_version' => '5.2.4',
                'mysql_version' => '5.0',
                'new_bundled' => '4.1',
                'partial_version' => '4.2.1',
                'support_email' => 'updatehelp42@wordpress.org',
                'new_files' => '',
             ),
          ),
        );
      });
      """

    When I run `wp core download --version=4.2.1 --force`
    And I run `wp core update`
    Then STDOUT should not be empty
    And the {SUITE_CACHE_DIR}/core directory should contain:
      """
      wordpress-4.2.1-en_US.tar.gz
      wordpress-4.2.4-partial-1-en_US.zip
      """

    When I run `wp core download --version=4.1.1 --force`
    And I run `wp core update`
    And I run `wp core verify-checksums`
    Then STDOUT should be:
      """
      Success: WordPress install verifies against checksums.
      """
    And the {SUITE_CACHE_DIR}/core directory should contain:
      """
      wordpress-4.1.1-en_US.tar.gz
      wordpress-4.2.1-en_US.tar.gz
      wordpress-4.2.4-no-content-en_US.zip
      wordpress-4.2.4-partial-1-en_US.zip
      """
