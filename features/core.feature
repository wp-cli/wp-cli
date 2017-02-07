Feature: Manage WordPress installation

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

  Scenario: Install WordPress by prompting for the admin email and password
    Given an empty directory
    And WP files
    And wp-config.php
    And a database
    And a session file:
      """
      wpcli
      admin@example.com
      """

    When I run `wp core install --url=localhost:8001 --title=Test --admin_user=wpcli --prompt=admin_email,admin_password < session`
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
    Then STDOUT should be:
      """
      Set up multisite database tables.
      Added multisite constants to 'wp-config.php'.
      Success: Network installed. Don't forget to set up rewrite rules.
      """
    And STDERR should be empty

    When I run `wp eval 'var_export( is_multisite() );'`
    Then STDOUT should be:
      """
      true
      """

    When I run `wp core is-installed --network`
    Then the return code should be 0

    When I try `wp core install-network --title='test network'`
    Then the return code should be 1

    When I run `wp network meta get 1 upload_space_check_disabled`
    Then STDOUT should be:
      """
      1
      """

  Scenario: Install multisite from scratch
    Given an empty directory
    And WP files
    And wp-config.php
    And a database

    When I run `wp core multisite-install --url=foobar.org --title=Test --admin_user=wpcli --admin_email=admin@example.com --admin_password=1`
    Then STDOUT should be:
      """
      Created single site database tables.
      Set up multisite database tables.
      Added multisite constants to 'wp-config.php'.
      Success: Network installed. Don't forget to set up rewrite rules.
      """
    And STDERR should be empty

    When I run `wp eval 'echo $GLOBALS["current_site"]->domain;'`
    Then STDOUT should be:
      """
      foobar.org
      """

    # Can complain that it's already installed, but don't exit with an error code
    When I try `wp core multisite-install --url=foobar.org --title=Test --admin_user=wpcli --admin_email=admin@example.com --admin_password=1`
    Then the return code should be 0

    When I run `wp network meta get 1 upload_space_check_disabled`
    Then STDOUT should be:
      """
      1
      """

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

  Scenario: Custom wp-content directory
    Given a WP install
    And a custom wp-content directory

    When I run `wp plugin status hello`
    Then STDOUT should not be empty

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

  Scenario: Test output in a multisite install with custom base path
    Given a WP install

    When I run `wp core multisite-convert --title=Test --base=/test/`
    And I run `wp post list`
    Then STDOUT should contain:
      """
      Hello world!
      """

  Scenario: Download WordPress
    Given an empty directory

    When I run `wp core download`
    Then STDOUT should contain:
     """
     Success: WordPress downloaded.
     """
    And the wp-settings.php file should exist

  Scenario: Don't download WordPress when files are already present
    Given an empty directory
    And WP files

    When I try `wp core download`
    Then STDERR should be:
      """
      Error: WordPress files seem to already be present here.
      """

  Scenario: Install WordPress in a subdirectory
    Given an empty directory
    And a wp-config.php file:
      """
      <?php
      // ** MySQL settings ** //
      /** The name of the database for WordPress */
      define('DB_NAME', 'wp_cli_test');

      /** MySQL database username */
      define('DB_USER', 'wp_cli_test');

      /** MySQL database password */
      define('DB_PASSWORD', 'password1');

      /** MySQL hostname */
      define('DB_HOST', '127.0.0.1');

      /** Database Charset to use in creating database tables. */
      define('DB_CHARSET', 'utf8');

      /** The Database Collate type. Don't change this if in doubt. */
      define('DB_COLLATE', '');

      $table_prefix = 'wp_';

      /* That's all, stop editing! Happy blogging. */

      /** Absolute path to the WordPress directory. */
      if ( !defined('ABSPATH') )
          define('ABSPATH', dirname(__FILE__) . '/');

      /** Sets up WordPress vars and included files. */
      require_once(ABSPATH . 'wp-settings.php');
      """
    And a wp-cli.yml file:
      """
      path: wp
      """

    When I run `wp core download`
    Then the wp directory should exist
    And the wp/wp-blog-header.php file should exist

    When I run `wp db create`
    And I run `wp core install --url=wp.dev --title="WP Dev" --admin_user=wpcli --admin_password=wpcli --admin_email=wpcli@example.com`
    Then STDOUT should not be empty

    When I run `wp option get home`
    Then STDOUT should be:
      """
      http://wp.dev
      """

    When I run `wp option get siteurl`
    Then STDOUT should be:
      """
      http://wp.dev
      """

  Scenario: Warn when multisite constants can't be inserted into wp-config
    Given a WP install
    And "That's all" replaced with "C'est tout" in the wp-config.php file

    When I run `wp core multisite-convert`
    Then STDOUT should be:
      """
      Set up multisite database tables.
      Success: Network installed. Don't forget to set up rewrite rules.
      """
    And STDERR should contain:
      """
      Warning: Multisite constants could not be written to 'wp-config.php'. You may need to add them manually:
      """
