Feature: Update WordPress core

  @less-than-php-7
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
      Cleaning up files...
      No files found that need cleaned up.
      Success: WordPress updated successfully.
      """

    When I run `wp eval 'echo $GLOBALS["wp_version"];'`
    Then STDOUT should be:
      """
      3.9
      """

  @less-than-php-7
  Scenario: Update to the latest minor release
    Given a WP install

    When I run `wp core download --version=3.7.9 --force`
    Then STDOUT should not be empty

    When I run `wp core update --minor`
    Then STDOUT should contain:
      """
      Updating to version 3.7.18
      """

    When I run `wp core update --minor`
    Then STDOUT should be:
      """
      Success: WordPress is at the latest minor release.
      """

    When I run `wp core version`
    Then STDOUT should be:
      """
      3.7.18
      """

  @less-than-php-7
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

  Scenario: Make sure files are cleaned up
    Given a WP install
    When I run `wp core update --version=4.4 --force`
    Then the wp-includes/rest-api.php file should exist
    Then the wp-includes/class-wp-comment.php file should exist
    And STDOUT should not contain:
      """
      File removed: wp-content
      """

    When I run `wp core update --version=4.3.2 --force`
    Then the wp-includes/rest-api.php file should not exist
    Then the wp-includes/class-wp-comment.php file should not exist
    Then STDOUT should contain:
      """
      File removed: wp-includes/class-walker-comment.php
      File removed: wp-includes/class-wp-network.php
      File removed: wp-includes/embed-template.php
      File removed: wp-includes/class-wp-comment.php
      File removed: wp-includes/class-wp-http-response.php
      File removed: wp-includes/class-walker-category-dropdown.php
      File removed: wp-includes/rest-api.php
      """
    And STDOUT should not contain:
      """
      File removed: wp-content
      """

    When I run `wp option add str_opt 'bar'`
    Then STDOUT should not be empty
    When I run `wp post create --post_title='Test post' --porcelain`
    Then STDOUT should be a number

  @less-than-php-7 @require-wp-4.0
  Scenario: Minor update on an unlocalized WordPress release
    Given a WP install
    And an empty cache

    When I run `wp core download --version=4.0 --locale=es_ES --force`
    Then STDOUT should contain:
      """
      Success: WordPress downloaded.
      """

    When I run `wp core update --minor`
    Then STDOUT should contain:
      """
      Updating to version 4.0.15 (en_US)...
      Descargando paquete de instalación desde https://downloads.wordpress.org/release/wordpress-4.0.15-partial-0.zip
      """
    And STDOUT should contain:
      """
      Success: WordPress updated successfully.
      """

  Scenario Outline: Use `--version=(nightly|trunk)` to update to the latest nightly version
    Given a WP install

    When I run `wp core update --version=<version>`
    Then STDOUT should contain:
      """
      Updating to version nightly (en_US)...
      Downloading update from https://wordpress.org/nightly-builds/wordpress-latest.zip...
      """
    And STDOUT should contain:
      """
      Success: WordPress updated successfully.
      """

    Examples:
    | version    |
    | trunk      |
    | nightly    |
