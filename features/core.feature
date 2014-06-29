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
      missing --url parameter
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

  Scenario: Full install
    Given a WP install

    When I run `wp core is-installed`
    Then STDOUT should be empty

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
    Then STDOUT should be empty

    When I run `wp core update wordpress-3.9.zip`
    Then STDOUT should be:
      """
      Unpacking the update...
      Success: WordPress updated successfully.
      """

    When I run `wp eval 'echo $GLOBALS["wp_version"];'`
    Then STDOUT should be:
      """
      3.9
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
