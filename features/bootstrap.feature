Feature: Bootstrap WP-CLI

  Background:
    When I run `wp package path`
    And save STDOUT as {PACKAGE_PATH}
    And I run `rm -rf {PACKAGE_PATH}/vendor`
    And I run `rm -rf {PACKAGE_PATH}/composer.json`
    And I run `rm -rf {PACKAGE_PATH}/composer.lock`

  @less-than-php-7.4 @require-opcache-save-comments
  Scenario: Basic Composer stack
    Given an empty directory
    And a composer.json file:
      """
      {
          "name": "wp-cli/composer-test",
          "type": "project",
          "require": {
              "wp-cli/wp-cli": "1.1.0"
          }
      }
      """
    # Note: Composer outputs messages to stderr.
    And I run `composer install --no-interaction 2>&1`

    When I run `vendor/bin/wp cli version`
    Then STDOUT should contain:
      """
      WP-CLI 1.1.0
      """

  Scenario: Composer stack with override requirement before WP-CLI
    Given a WP installation
    And a composer.json file:
      """
      {
        "name": "wp-cli/composer-test",
        "type": "project",
        "minimum-stability": "dev",
        "prefer-stable": true,
        "repositories": [
          {
            "type": "path",
            "url": "./override",
            "options": {
                "symlink": false
            }
          }
        ],
        "require": {
          "wp-cli/override": "*",
          "wp-cli/wp-cli": "dev-main"
        }
      }
      """
    And a override/override.php file:
      """
      <?php
      if ( ! class_exists( 'WP_CLI' ) ) {
        return;
      }
      // Override bundled command.
      WP_CLI::add_command( 'eval', 'Eval_Command', array( 'when' => 'before_wp_load' ) );
      """
    And a override/src/Eval_Command.php file:
      """
      <?php
      class Eval_Command extends WP_CLI_Command {
        public function __invoke() {
          WP_CLI::success( "WP-Override-Eval" );
        }
      }
      """
    And a override/composer.json file:
      """
      {
        "name": "wp-cli/override",
        "description": "A command that overrides the 'eval' command.",
        "autoload": {
          "psr-4": { "": "src/" },
          "files": [ "override.php" ]
        },
        "extra": {
          "commands": [
            "eval"
          ]
        }
     }
      """
    And I run `composer install --no-interaction 2>&1`

    When I run `vendor/bin/wp eval '\WP_CLI::Success( "WP-Standard-Eval" );'`
    Then STDOUT should contain:
      """
      Success: WP-Override-Eval
      """

  Scenario: Override command bundled with current source

    Given a WP installation
    And a override/override.php file:
      """
      <?php
      if ( ! class_exists( 'WP_CLI' ) ) {
        return;
      }
      $autoload = dirname( __FILE__ ) . '/vendor/autoload.php';
      if ( file_exists( $autoload ) && ! class_exists( 'CLI_Command' ) ) {
        require_once $autoload;
      }
      // Override framework command.
      WP_CLI::add_command( 'cli', 'CLI_Command', array( 'when' => 'before_wp_load' ) );
      // Override bundled command.
      WP_CLI::add_command( 'eval', 'Eval_Command', array( 'when' => 'before_wp_load' ) );
      """
    And a override/src/CLI_Command.php file:
      """
      <?php
      class CLI_Command extends WP_CLI_Command {
        public function version() {
          WP_CLI::success( "WP-Override-CLI" );
        }
      }
      """
    And a override/src/Eval_Command.php file:
      """
      <?php
      class Eval_Command extends WP_CLI_Command {
        public function __invoke() {
          WP_CLI::success( "WP-Override-Eval" );
        }
      }
      """
    And a override/composer.json file:
      """
      {
        "name": "wp-cli/override",
        "description": "A command that overrides the bundled 'cli' and 'eval' commands.",
        "autoload": {
          "psr-4": { "": "src/" },
          "files": [ "override.php" ]
        },
        "extra": {
          "commands": [
            "cli",
            "eval"
          ]
        }
      }
      """
    And I run `composer install --working-dir={RUN_DIR}/override --no-interaction 2>&1`

    When I run `wp cli version`
    Then STDOUT should contain:
      """
      WP-CLI
      """

    When I run `wp eval '\WP_CLI::Success( "WP-Standard-Eval" );'`
    Then STDOUT should contain:
      """
      Success: WP-Standard-Eval
      """

    When I run `wp --require=override/override.php cli version`
    Then STDOUT should contain:
      """
      WP-Override-CLI
      """

    When I run `wp --require=override/override.php eval '\WP_CLI::Success( "WP-Standard-Eval" );'`
    Then STDOUT should contain:
      """
      Success: WP-Override-Eval
      """

  Scenario: Override command through package manager

    Given a WP installation
    And a override/override.php file:
      """
      <?php
      if ( ! class_exists( 'WP_CLI' ) ) {
        return;
      }
      $autoload = dirname( __FILE__ ) . '/vendor/autoload.php';
      if ( file_exists( $autoload ) && ! class_exists( 'CLI_Command' ) ) {
        require_once $autoload;
      }
      // Override framework command.
      WP_CLI::add_command( 'cli', 'CLI_Command', array( 'when' => 'before_wp_load' ) );
      // Override bundled command.
      WP_CLI::add_command( 'eval', 'Eval_Command', array( 'when' => 'before_wp_load' ) );
      """
    And a override/src/CLI_Command.php file:
      """
      <?php
      class CLI_Command extends WP_CLI_Command {
        public function version() {
          WP_CLI::success( "WP-Override-CLI" );
        }
      }
      """
    And a override/src/Eval_Command.php file:
      """
      <?php
      class Eval_Command extends WP_CLI_Command {
        public function __invoke() {
          WP_CLI::success( "WP-Override-Eval" );
        }
      }
      """
    And a override/composer.json file:
      """
      {
        "name": "wp-cli/override",
        "description": "A command that overrides the bundled 'cli' and 'eval' commands.",
        "autoload": {
          "psr-4": { "": "src/" },
          "files": [ "override.php" ]
        },
        "extra": {
          "commands": [
            "cli",
            "eval"
          ]
        }
      }
      """
    And I run `wp package install {RUN_DIR}/override`

    When I run `wp cli version --skip-packages`
    Then STDOUT should contain:
      """
      WP-CLI
      """

    When I run `wp eval '\WP_CLI::Success( "WP-Standard-Eval" );' --skip-packages`
    Then STDOUT should contain:
      """
      Success: WP-Standard-Eval
      """

    When I run `wp cli version`
    Then STDOUT should contain:
      """
      WP-Override-CLI
      """

    When I run `wp eval '\WP_CLI::Success( "WP-Standard-Eval" );'`
    Then STDOUT should contain:
      """
      Success: WP-Override-Eval
      """

  Scenario: Extend existing bundled command through package manager

    Given a WP installation
    And a override/override.php file:
      """
      <?php
      if ( ! class_exists( 'WP_CLI' ) ) {
        return;
      }

      WP_CLI::add_hook( 'before_wp_load', static function () {
        WP_CLI::add_command( 'plugin', 'My_Extended_Plugin_Command' );
      } );
      """
    And a override/src/My_Extended_Plugin_Command.php file:
      """
      <?php
      class My_Extended_Plugin_Command extends Plugin_Command {
        public function install( $args, $assoc_args ) {
          WP_CLI::error( 'Plugin installation has been disabled.' );
        }
      }
      """
    And a override/composer.json file:
      """
      {
        "name": "wp-cli/override",
        "description": "A command that extends the bundled 'plugin' command.",
        "autoload": {
          "psr-4": { "": "src/" },
          "files": [ "override.php" ]
        },
        "extra": {
          "commands": [
            "plugin"
          ]
        }
      }
      """
    And I run `wp package install {RUN_DIR}/override`

    When I try `wp plugin install duplicate-post`
    Then STDERR should contain:
      """
      Error: Plugin installation has been disabled.
      """

    When I run `wp plugin list`
    Then STDOUT should contain:
      """
      hello
      """

  Scenario: Define constant before running a command

    Given a WP installation

    # Expect a warning from WP core for PHP 8+.
    When I try `wp --exec="define( 'WP_ADMIN', true );" eval "echo WP_ADMIN;"`
    Then STDOUT should contain:
      """
      1
      """

  @require-php-7.0
  Scenario: Composer stack with both WordPress and wp-cli as dependencies (command line)
    Given a WP installation with Composer
    And a dependency on current wp-cli
    # Redirect STDERR to STDOUT as Composer produces non-error output on STDERR
    And I run `composer require wp-cli/entity-command --with-all-dependencies --no-interaction 2>&1`

    When I run `vendor/bin/wp option get blogname`
    Then STDOUT should contain:
      """
      WP CLI Site with both WordPress and wp-cli as Composer dependencies
      """

  @broken @require-php-7.0
  Scenario: Composer stack with both WordPress and wp-cli as dependencies (web)
    Given a WP installation with Composer
    And a dependency on current wp-cli
    And a PHP built-in web server to serve 'WordPress'
    Then the HTTP status code should be 200

  @require-php-7.0
  Scenario: Composer stack with both WordPress and wp-cli as dependencies and a custom vendor directory
    Given a WP installation with Composer and a custom vendor directory 'vendor-custom'
    And a dependency on current wp-cli
    # Redirect STDERR to STDOUT as Composer produces non-error output on STDERR
    And I run `composer require wp-cli/entity-command --with-all-dependencies --no-interaction 2>&1`

    When I run `vendor-custom/bin/wp option get blogname`
    Then STDOUT should contain:
      """
      WP CLI Site with both WordPress and wp-cli as Composer dependencies
      """

  Scenario: Setting an environment variable passes the value through
    Given an empty directory
    And WP files
    And a database
    And a env-var.php file:
      """
      <?php
      putenv( 'WP_CLI_TEST_ENV_VAR=foo' );
      """
    And a wp-cli.yml file:
      """
      config create:
        extra-php: |
          require_once __DIR__ . '/env-var.php';
          define( 'WP_CLI_TEST_CONSTANT', getenv( 'WP_CLI_TEST_ENV_VAR' ) );
      """

    When I run `wp config create --skip-check {CORE_CONFIG_SETTINGS}`
    Then STDOUT should contain:
      """
      Success:
      """

    # Use try to cater for wp-db errors in old WPs.
    When I try `wp core install --url=example.com --title=example --admin_user=example --admin_email=example@example.org`
    Then STDOUT should contain:
      """
      Success:
      """
    And the return code should be 0

    When I run `wp eval 'echo constant( "WP_CLI_TEST_CONSTANT" );'`
    Then STDOUT should be:
      """
      foo
      """

  @require-wp-3.9
  Scenario: Run cache flush on ms_site_not_found
    Given a WP multisite installation
    And a wp-cli.yml file:
      """
      url: invalid.com
      """
    And I run `wp package install wp-cli/cache-command`

    When I try `wp cache add foo bar`
    Then STDERR should contain:
      """
      Error: Site 'invalid.com' not found.
      """
    And the return code should be 1

    When I try `wp cache flush --url=invalid.com`
    Then STDOUT should contain:
      """
      Success: The cache was flushed.
      """
    And the return code should be 0

  # `wp search-replace` does not yet support SQLite
  # See https://github.com/wp-cli/search-replace-command/issues/190
  @require-wp-4.0 @require-mysql
  Scenario: Run search-replace on ms_site_not_found
    Given a WP multisite installation
    And a wp-cli.yml file:
      """
      url: invalid.com
      """
    And I run `wp package install wp-cli/search-replace-command`

    When I try `wp search-replace foo bar`
    Then STDERR should contain:
      """
      Error: Site 'invalid.com' not found.
      """
    And the return code should be 1

    When I run `wp option update test_key '["foo"]' --format=json --url=example.com`
    Then STDOUT should contain:
      """
      Success:
      """

    # --network should permit search-replace
    When I run `wp search-replace foo bar --network`
    Then STDOUT should contain:
      """
      Success:
      """
    And the return code should be 0

    When I run `wp option update test_key '["foo"]' --format=json --url=example.com`
    Then STDOUT should contain:
      """
      Success:
      """

    # --all-tables should permit search-replace
    When I run `wp search-replace foo bar --all-tables`
    Then STDOUT should contain:
      """
      Success:
      """
    And the return code should be 0

    When I run `wp option update test_key '["foo"]' --format=json --url=example.com`
    Then STDOUT should contain:
      """
      Success:
      """

    # --all-tables-with-prefix should permit search-replace
    When I run `wp search-replace foo bar --all-tables-with-prefix`
    Then STDOUT should contain:
      """
      Success:
      """
    And the return code should be 0

    When I run `wp option update test_key '["foo"]' --format=json --url=example.com`
    Then STDOUT should contain:
      """
      Success:
      """

    # Specific tables should permit search-replace
    When I run `wp search-replace foo bar wp_options`
    Then STDOUT should contain:
      """
      Success:
      """
    And the return code should be 0

  Scenario: Allow disabling ini_set()
    Given an empty directory
    When I try `{INVOKE_WP_CLI_WITH_PHP_ARGS--ddisable_functions=ini_set} cli info`
    Then the return code should be 0
