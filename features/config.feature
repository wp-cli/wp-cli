Feature: Have a config file

  Scenario: No config file
    Given a WP installation

    When I run `wp --info`
    Then STDOUT should not contain:
      """
      wp-cli.yml
      """

    When I run `wp core is-installed` from 'wp-content'
    Then STDOUT should be empty

  Scenario: Config file in WP Root
    Given a WP installation
    And a sample.php file:
      """
      <?php
      """
    And a wp-cli.yml file:
      """
      require: sample.php
      """

    When I run `wp --info`
    Then STDOUT should contain:
      """
      wp-cli.yml
      """

    When I run `wp core is-installed`
    Then STDOUT should be empty

    When I run `wp` from 'wp-content'
    Then STDOUT should not be empty

  Scenario: WP in a subdirectory
    Given a WP installation in 'foo'
    And a wp-cli.yml file:
      """
      path: foo
      """

    When I run `wp --info`
    Then STDOUT should contain:
      """
      wp-cli.yml
      """

    When I run `wp core is-installed`
    Then STDOUT should be empty

    When I run `wp core is-installed` from 'foo/wp-content'
    Then STDOUT should be empty

    When I run `mkdir -p other/subdir`
    And I run `wp core is-installed` from 'other/subdir'
    Then STDOUT should be empty

  Scenario: WP in a subdirectory (autodetected)
    Given a WP installation in 'foo'

    Given an index.php file:
    """
    require('./foo/wp-blog-header.php');
    """
    When I run `wp core is-installed`
    Then STDOUT should be empty

    Given an index.php file:
    """
    require dirname(__FILE__) . '/foo/wp-blog-header.php';
    """
    When I run `wp core is-installed`
    Then STDOUT should be empty

    When I run `mkdir -p other/subdir`
    And I run `echo '<?php // Silence is golden' > other/subdir/index.php`
    And I run `wp core is-installed` from 'other/subdir'
    Then STDOUT should be empty

  Scenario: Nested installs
    Given a WP installation
    And a WP install in 'foo'
    And a wp-cli.yml file:
      """
      """

    When I run `wp --info` from 'foo'
    Then STDOUT should not contain:
      """
      wp-cli.yml
      """

  Scenario: Disabled commands
    Given a WP installation
    And a config.yml file:
      """
      disabled_commands:
        - eval-file
        - core multisite-convert
      """

    When I run `WP_CLI_CONFIG_PATH=config.yml wp`
    Then STDOUT should not contain:
      """
      eval-file
      """

    When I try `WP_CLI_CONFIG_PATH=config.yml wp help eval-file`
    Then STDERR should be:
      """
      Error: The 'eval-file' command has been disabled from the config file.
      """

    When I run `WP_CLI_CONFIG_PATH=config.yml wp core`
    Then STDOUT should not contain:
      """
      or: wp core multisite-convert
      """

    When I run `WP_CLI_CONFIG_PATH=config.yml wp help core`
    Then STDOUT should not contain:
      """
      multisite-convert
      """

    When I try `WP_CLI_CONFIG_PATH=config.yml wp core multisite-convert`
    Then STDERR should contain:
      """
      command has been disabled
      """

    When I try `WP_CLI_CONFIG_PATH=config.yml wp help core multisite-convert`
    Then STDERR should contain:
      """
      Error: The 'core multisite-convert' command has been disabled from the config file.
      """

  Scenario: 'core config' parameters
    Given an empty directory
    And WP files
    And a wp-cli.yml file:
      """
      core config:
        dbname: wordpress
        dbuser: root
        extra-php: |
          define( 'WP_DEBUG', true );
          define( 'WP_POST_REVISIONS', 50 );
      """

    When I run `wp core config --skip-check`
    And I run `grep WP_POST_REVISIONS wp-config.php`
    Then STDOUT should not be empty

  Scenario: Persist positional parameters when defined in a config
    Given a WP installation
    And a wp-cli.yml file:
      """
      user create:
        - examplejoe
        - joe@example.com
        user_pass: joe
        role: administrator
      """

    When I run `wp user create`
    Then STDOUT should not be empty

    When I run `wp user get examplejoe --field=roles`
    Then STDOUT should contain:
      """
      administrator
      """

    When I try `wp user create examplejane`
    Then STDERR should be:
      """
      Error: Sorry, that email address is already used!
      """

    When I run `wp user create examplejane jane@example.com`
    Then STDOUT should not be empty

    When I run `wp user get examplejane --field=roles`
    Then STDOUT should contain:
      """
      administrator
      """

  Scenario: Command-specific configs
    Given a WP installation
    And a wp-cli.yml file:
      """
      eval:
        foo: bar
      post list:
        format: count
      """

    # Arbitrary values should be passed, without warnings
    When I run `wp eval 'echo json_encode( $assoc_args );'`
    Then STDOUT should be JSON containing:
      """
      {"foo": "bar"}
      """

    # CLI args should trump config values
    When I run `wp post list`
    Then STDOUT should be a number
    When I run `wp post list --format=json`
    Then STDOUT should not be a number

  Scenario: Required files should not be loaded twice
    Given an empty directory
    And a custom-file.php file:
      """
      <?php
      define( 'FOOBUG', 'BAR' );
      """
    And a test-dir/config.yml file:
      """
      require:
        - ../custom-file.php
      """
    And a wp-cli.yml file:
      """
      require:
        - custom-file.php
      """

    When I run `WP_CLI_CONFIG_PATH=test-dir/config.yml wp help`
	  Then STDERR should be empty

  Scenario: Load WordPress with `--debug`
    Given a WP installation

    When I try `wp option get home --debug`
    Then STDERR should contain:
      """
      No readable global config found
      """
    Then STDERR should contain:
      """
      No project config found
      """
    And STDERR should contain:
      """
      Begin WordPress load
      """
    And STDERR should contain:
      """
      wp-config.php path:
      """
    And STDERR should contain:
      """
      Loaded WordPress
      """
    And STDERR should contain:
      """
      Running command: option get
      """
    And the return code should be 0

    When I try `wp option get home --debug=bootstrap`
    Then STDERR should contain:
      """
      No readable global config found
      """
    Then STDERR should contain:
      """
      No project config found
      """
    And STDERR should contain:
      """
      Begin WordPress load
      """
    And STDERR should contain:
      """
      wp-config.php path:
      """
    And STDERR should contain:
      """
      Loaded WordPress
      """
    And STDERR should contain:
      """
      Running command: option get
      """
    And the return code should be 0

    When I try `wp option get home --debug=foo`
    Then STDERR should not contain:
      """
      No readable global config found
      """
    Then STDERR should not contain:
      """
      No project config found
      """
    And STDERR should not contain:
      """
      Begin WordPress load
      """
    And STDERR should not contain:
      """
      wp-config.php path:
      """
    And STDERR should not contain:
      """
      Loaded WordPress
      """
    And STDERR should not contain:
      """
      Running command: option get
      """
    And the return code should be 0

  Scenario: Missing required files should not fatal WP-CLI
    Given an empty directory
    And a wp-cli.yml file:
    """
    require:
      - missing-file.php
    """

    When I try `wp help`
    Then STDERR should contain:
      """
      Error: Required file 'missing-file.php' doesn't exist (from project's wp-cli.yml).
      """

    When I run `wp cli info`
    Then STDOUT should not be empty

    When I run `wp --info`
    Then STDOUT should not be empty

  Scenario: Missing required file in global config
    Given an empty directory
    And a config.yml file:
      """
      require:
        - /foo/baz.php
      """

    When I try `WP_CLI_CONFIG_PATH=config.yml wp help`
    Then STDERR should contain:
      """
      Error: Required file 'baz.php' doesn't exist (from global config.yml).
      """

  Scenario: Missing required file as runtime argument
    Given an empty directory

    When I try `wp help --require=foo.php`
    Then STDERR should contain:
      """
      Error: Required file 'foo.php' doesn't exist (from runtime argument).
      """

  Scenario: Config inheritance from project to global
    Given an empty directory
    And a test-cmd.php file:
      """
      <?php
      $command = function( $_, $assoc_args ) {
         echo json_encode( $assoc_args );
      };
      WP_CLI::add_command( 'test-cmd', $command, array( 'when' => 'before_wp_load' ) );
      """
    And a config.yml file:
      """
      test-cmd:
        foo: bar
        apple: banana
      apple: banana
      """
    And a wp-cli.yml file:
      """
      _:
        merge: true
      test-cmd:
        bar: burrito
        apple: apple
      apple: apple
      """

    When I run `wp --require=test-cmd.php test-cmd`
    Then STDOUT should be JSON containing:
      """
      {"bar":"burrito","apple":"apple"}
      """
    When I run `WP_CLI_CONFIG_PATH=config.yml wp --require=test-cmd.php test-cmd`
    Then STDOUT should be JSON containing:
      """
      {"foo":"bar","apple":"apple","bar":"burrito"}
      """

    Given a wp-cli.yml file:
      """
      _:
        merge: false
      test-cmd:
        bar: burrito
        apple: apple
      apple: apple
      """
    When I run `WP_CLI_CONFIG_PATH=config.yml wp --require=test-cmd.php test-cmd`
    Then STDOUT should be JSON containing:
      """
      {"bar":"burrito","apple":"apple"}
      """

  Scenario: Config inheritance from local to project
    Given an empty directory
    And a test-cmd.php file:
      """
      <?php
      $command = function( $_, $assoc_args ) {
         echo json_encode( $assoc_args );
      };
      WP_CLI::add_command( 'test-cmd', $command, array( 'when' => 'before_wp_load' ) );
      """
    And a wp-cli.yml file:
      """
      test-cmd:
        foo: bar
        apple: banana
      apple: banana
      """

    When I run `wp --require=test-cmd.php test-cmd`
    Then STDOUT should be JSON containing:
      """
      {"foo":"bar","apple":"banana"}
      """

    Given a wp-cli.local.yml file:
      """
      _:
        inherit: wp-cli.yml
        merge: true
      test-cmd:
        bar: burrito
        apple: apple
      apple: apple
      """

    When I run `wp --require=test-cmd.php test-cmd`
    Then STDOUT should be JSON containing:
      """
      {"foo":"bar","apple":"apple","bar":"burrito"}
      """

    Given a wp-cli.local.yml file:
      """
      test-cmd:
        bar: burrito
        apple: apple
      apple: apple
      """

    When I run `wp --require=test-cmd.php test-cmd`
    Then STDOUT should be JSON containing:
      """
      {"bar":"burrito","apple":"apple"}
      """

  @require-wp-3.9
  Scenario: WordPress install with local dev DOMAIN_CURRENT_SITE
    Given a WP multisite installation
    And a local-dev.php file:
      """
      <?php
      define( 'DOMAIN_CURRENT_SITE', 'example.dev' );
      """
    And a wp-config.php file:
      """
<?php
if ( file_exists( __DIR__ . '/local-dev.php' ) ) {
  require_once __DIR__ . '/local-dev.php';
}

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

define( 'WP_ALLOW_MULTISITE', true );
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', false);
$base = '/';
if ( ! defined( 'DOMAIN_CURRENT_SITE' ) ) {
  define('DOMAIN_CURRENT_SITE', 'example.com');
}
define('PATH_CURRENT_SITE', '/');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
  define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
      """

    When I try `wp option get home`
    Then STDERR should be:
      """
      Error: Site 'example.dev/' not found. Verify DOMAIN_CURRENT_SITE matches an existing site or use `--url=<url>` to override.
      """

    When I run `wp option get home --url=example.com`
    Then STDOUT should be:
      """
      http://example.com
      """
