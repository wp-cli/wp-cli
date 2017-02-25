Feature: Create a new site on a WP multisite

  Scenario: Respect defined `$base` in wp-config
    Given an empty directory
    And WP files
    And a database
    And a extra-config file:
      """
      define( 'WP_ALLOW_MULTISITE', true );
      define( 'MULTISITE', true );
      define( 'SUBDOMAIN_INSTALL', false );
      $base = '/dev/';
      define( 'DOMAIN_CURRENT_SITE', 'localhost' );
      define( 'PATH_CURRENT_SITE', '/dev/' );
      define( 'SITE_ID_CURRENT_SITE', 1 );
      define( 'BLOG_ID_CURRENT_SITE', 1 );
      """

    When I run `wp core config {CORE_CONFIG_SETTINGS} --extra-php < extra-config`
    Then STDOUT should be:
      """
      Success: Generated 'wp-config.php' file.
      """

    When I run `wp core multisite-install --url=localhost/dev/ --title=Test --admin_user=admin --admin_email=admin@example.org`
    Then STDOUT should contain:
      """
      Success: Network installed. Don't forget to set up rewrite rules.
      """

    When I run `wp site list --fields=blog_id,url`
    Then STDOUT should be a table containing rows:
      | blog_id | url                   |
      | 1       | http://localhost/dev/ |

    When I run `wp site create --slug=newsite`
    Then STDOUT should be:
      """
      Success: Site 2 created: http://localhost/dev/newsite/
      """

    When I run `wp site list --fields=blog_id,url`
    Then STDOUT should be a table containing rows:
      | blog_id | url                           |
      | 1       | http://localhost/dev/         |
      | 2       | http://localhost/dev/newsite/ |
