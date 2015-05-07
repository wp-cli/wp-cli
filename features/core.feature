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
    And the {SUITE_CACHE_DIR}/core/en_US-{VERSION}.tar.gz file should exist

    When I run `mkdir inner`
    And I run `cd inner && wp core download`
    Then the inner/wp-settings.php file should exist

    # test core tarball cache
    When I run `wp core download --force`
    Then the wp-settings.php file should exist
    And STDOUT should contain:
    """
    Using cached file '{SUITE_CACHE_DIR}/core/en_US-{VERSION}.tar.gz'...
    """

  @download
  Scenario: Localized install
    Given an empty directory
    And an empty cache
    When I run `wp core download --locale=de_DE`
    And save STDOUT 'Downloading WordPress ([\d\.]+)' as {VERSION}
    Then the wp-settings.php file should exist
    And the {SUITE_CACHE_DIR}/core/de_DE-{VERSION}.tar.gz file should exist

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

  @download
  Scenario: Check for update via Version Check API
    Given a WP install

    When I run `wp core download --version=3.8 --force`
    Then STDOUT should not be empty

    When I run `wp core check-update`
    Then STDOUT should be a table containing rows:
      | version | update_type | package_url                               |
      | 4.2.2   | major       | https://wordpress.org/wordpress-4.2.2.zip |
      | 4.1.5   | major       | https://wordpress.org/wordpress-4.1.5.zip |
      | 4.0.5   | major       | https://wordpress.org/wordpress-4.0.5.zip |
      | 3.9.6   | major       | https://wordpress.org/wordpress-3.9.6.zip |
      | 3.8.8   | minor       | https://wordpress.org/wordpress-3.8.8.zip |

    When I run `wp core check-update --format=count`
    Then STDOUT should be:
      """
      5
      """

    When I run `wp core check-update --major`
    Then STDOUT should be a table containing rows:
      | version | update_type | package_url                               |
      | 4.2.2   | major       | https://wordpress.org/wordpress-4.2.2.zip |
      | 4.1.5   | major       | https://wordpress.org/wordpress-4.1.5.zip |
      | 4.0.5   | major       | https://wordpress.org/wordpress-4.0.5.zip |
      | 3.9.6   | major       | https://wordpress.org/wordpress-3.9.6.zip |

    When I run `wp core check-update --major --format=count`
    Then STDOUT should be:
      """
      4
      """

    When I run `wp core check-update --minor`
    Then STDOUT should be a table containing rows:
      | version | update_type | package_url                               |
      | 3.8.8   | minor       | https://wordpress.org/wordpress-3.8.8.zip |

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
      Using cached file '{SUITE_CACHE_DIR}/core/en_US-3.8.1.zip'...
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

  @require-wp-4.0
  Scenario: Core translation CRUD
    Given a WP install

    When I run `wp core language list --fields=language,english_name,status`
    Then STDOUT should be a table containing rows:
      | language  | english_name            | status        |
      | ar        | Arabic                  | uninstalled   |
      | az        | Azerbaijani             | uninstalled   |
      | en_US     | English (United States) | active        |
      | en_GB     | English (UK)            | uninstalled   |

    When I run `wp core language install en_GB`
    Then the wp-content/languages/admin-en_GB.po file should exist
    And the wp-content/languages/en_GB.po file should exist
    And STDOUT should be:
      """
      Success: Language installed.
      """

    When I try `wp core language install en_GB`
    Then STDERR should be:
      """
      Warning: Language already installed.
      """

    When I run `wp core language list --fields=language,english_name,status`
    Then STDOUT should be a table containing rows:
      | language  | english_name     | status        |
      | ar        | Arabic           | uninstalled   |
      | az        | Azerbaijani      | uninstalled   |
      | en_GB     | English (UK)     | installed     |

    When I run `wp core language activate en_GB`
    Then STDOUT should be:
      """
      Success: Language activated.
      """

    When I run `wp core language list --fields=language,english_name,update`
    Then STDOUT should be a table containing rows:
      | language  | english_name            | update        |
      | ar        | Arabic                  | none          |
      | az        | Azerbaijani             | none          |
      | en_US     | English (United States) | none          |
      | en_GB     | English (UK)            | available     |

    When I run `wp core language update --dry-run`
    Then save STDOUT 'Available (\d+) translations updates' as {UPDATES}

    When I run `wp core language update`
    Then STDOUT should contain:
      """
      Success: Updated {UPDATES}/{UPDATES} translations.
      """
    And the wp-content/languages/plugins directory should exist
    And the wp-content/languages/themes directory should exist

    When I run `wp core language list --field=language --status=active`
    Then STDOUT should be:
      """
      en_GB
      """

    When I run `wp core language list --fields=language,english_name,status`
    Then STDOUT should be a table containing rows:
      | language  | english_name     | status        |
      | ar        | Arabic           | uninstalled   |
      | az        | Azerbaijani      | uninstalled   |
      | en_GB     | English (UK)     | active        |

    When I run `wp core language activate en_US`
    Then STDOUT should be:
      """
      Success: Language activated.
      """

    When I run `wp core language list --fields=language,english_name,status`
    Then STDOUT should be a table containing rows:
      | language  | english_name            | status        |
      | ar        | Arabic                  | uninstalled   |
      | en_US     | English (United States) | active        |
      | en_GB     | English (UK)            | installed     |


    When I try `wp core language activate invalid_lang`
    Then STDERR should be:
      """
      Error: Language not installed.
      """

    When I run `wp core language uninstall en_GB`
    Then the wp-content/languages/admin-en_GB.po file should not exist
    And the wp-content/languages/en_GB.po file should not exist
    And STDOUT should be:
      """
      Success: Language uninstalled.
      """

    When I try `wp core language uninstall en_GB`
    Then STDERR should be:
      """
      Error: Language not installed.
      """

    When I run `wp core language install --activate en_GB`
    Then the wp-content/languages/admin-en_GB.po file should exist
    And the wp-content/languages/en_GB.po file should exist
    And STDOUT should be:
      """
      Success: Language installed.
      Success: Language activated.
      """

  @require-wp-4.0
  Scenario: Don't allow active language to be uninstalled
    Given a WP install

    When I run `wp core language install en_GB --activate`
    Then STDOUT should not be empty

    When I try `wp core language uninstall en_GB`
    Then STDERR should be:
      """
      Warning: The 'en_GB' language is active.
      """

  Scenario: Ensure file cache isn't corrupted by a ZIP masquerading as a gzipped TAR, part one
    Given a WP install
    And an empty cache
    And I run `mkdir -p {SUITE_CACHE_DIR}/core; wget -O {SUITE_CACHE_DIR}/core/en_US-4.0.tar.gz https://wordpress.org/wordpress-4.0.zip; touch {SUITE_CACHE_DIR}/core/en_US-4.0.tar.gz`

    When I run `wp core download --version=4.0 --force`
    Then STDOUT should contain:
      """
      Success: WordPress downloaded
      """
    And STDERR should contain:
      """
      Warning: Extraction failed, downloading a new copy...
      """

    When I run `wp core version`
    Then STDOUT should be:
      """
      4.0
      """

  Scenario: Ensure file cache isn't corrupted by core update, part two
    Given a WP install
    And an empty cache

    When I run `wp core download --version=4.0 --force`
    Then STDOUT should contain:
      """
      Success: WordPress downloaded
      """

    When I run `wp core version`
    Then STDOUT should be:
      """
      4.0
      """

    When I run `wp core update --version=4.0 --force`
    Then STDOUT should contain:
      """
      Success: WordPress updated successfully
      """

    When I run `wp core version`
    Then STDOUT should be:
      """
      4.0
      """

  Scenario: Catch download of non-existent WP version
    Given an empty directory

    When I try `wp core download --version=4.1.0 --force`
    Then STDERR should contain:
      """
      Error: Release not found.
      """
