Feature: Bootstrap WP-CLI

  @require-opcache-save-comments
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
    Given an empty directory
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
            "url": "./cli-override-command",
            "options": {
                "symlink": false
            }
          }
        ],
        "require": {
          "wp-cli/cli-override-command": "*",
          "wp-cli/wp-cli": "dev-master"
        }
      }
      """
    And a cli-override-command/cli.php file:
      """
      <?php
      if ( ! class_exists( 'WP_CLI' ) ) {
        return;
      }
      $autoload = dirname( __FILE__ ) . '/vendor/autoload.php';
      if ( file_exists( $autoload ) && ! class_exists( 'CLI_Command' ) ) {
        require_once $autoload;
      }
      WP_CLI::add_command( 'cli', 'CLI_Command', array( 'when' => 'before_wp_load' ) );
      """
    And a cli-override-command/src/CLI_Command.php file:
      """
      <?php
      class CLI_Command extends WP_CLI_Command {
        public function version() {
          WP_CLI::success( "WP-Override-CLI" );
        }
      }
      """
    And a cli-override-command/composer.json file:
      """
      {
        "name": "wp-cli/cli-override-command",
        "description": "A command that overrides the bundled 'cli' command.",
        "autoload": {
          "psr-4": { "": "src/" },
          "files": [ "cli.php" ]
        },
        "extra": {
          "commands": [
            "cli"
          ]
        }
     }
      """
    And I run `composer install --no-interaction 2>&1`

    When I run `vendor/bin/wp cli version`
    Then STDOUT should contain:
      """
      Success: WP-Override-CLI
      """

  Scenario: Override command bundled with current source

    Given an empty directory
    And a cli-override-command/cli.php file:
      """
      <?php
      if ( ! class_exists( 'WP_CLI' ) ) {
        return;
      }
      $autoload = dirname( __FILE__ ) . '/vendor/autoload.php';
      if ( file_exists( $autoload ) && ! class_exists( 'CLI_Command' ) ) {
        require_once $autoload;
      }
      WP_CLI::add_command( 'cli', 'CLI_Command', array( 'when' => 'before_wp_load' ) );
      """
    And a cli-override-command/src/CLI_Command.php file:
      """
      <?php
      class CLI_Command extends WP_CLI_Command {
        public function version() {
          WP_CLI::success( "WP-Override-CLI" );
        }
      }
      """
    And a cli-override-command/composer.json file:
      """
      {
        "name": "wp-cli/cli-override",
        "description": "A command that overrides the bundled 'cli' command.",
        "autoload": {
          "psr-4": { "": "src/" },
          "files": [ "cli.php" ]
        },
        "extra": {
          "commands": [
            "cli"
          ]
        }
      }
      """
    And I run `composer install --working-dir={RUN_DIR}/cli-override-command --no-interaction 2>&1`

    When I run `wp cli version`
      Then STDOUT should contain:
        """
        WP-CLI
        """

    When I run `wp --require=cli-override-command/cli.php cli version`
      Then STDOUT should contain:
        """
        WP-Override-CLI
        """

  Scenario: Override command bundled with freshly built PHAR

    Given an empty directory
    And a new Phar with the same version
    And a cli-override-command/cli.php file:
      """
      <?php
      if ( ! class_exists( 'WP_CLI' ) ) {
        return;
      }
      $autoload = dirname( __FILE__ ) . '/vendor/autoload.php';
      if ( file_exists( $autoload ) ) {
        require_once $autoload;
      }
      WP_CLI::add_command( 'cli', 'CLI_Command', array( 'when' => 'before_wp_load' ) );
      """
    And a cli-override-command/src/CLI_Command.php file:
      """
      <?php
      class CLI_Command extends WP_CLI_Command {
        public function version() {
          WP_CLI::success( "WP-Override-CLI" );
        }
      }
      """
    And a cli-override-command/composer.json file:
      """
      {
        "name": "wp-cli/cli-override",
        "description": "A command that overrides the bundled 'cli' command.",
        "autoload": {
          "psr-4": { "": "src/" },
          "files": [ "cli.php" ]
        },
        "extra": {
          "commands": [
            "cli"
          ]
        }
      }
      """
    And I run `composer install --working-dir={RUN_DIR}/cli-override-command --no-interaction 2>&1`

    When I run `{PHAR_PATH} cli version`
      Then STDOUT should contain:
        """
        WP-CLI
        """

    When I run `{PHAR_PATH} --require=cli-override-command/cli.php cli version`
      Then STDOUT should contain:
        """
        WP-Override-CLI
        """

  Scenario: Composer stack with both WordPress and wp-cli as dependencies (command line)
    Given a WP installation with Composer
    And a dependency on current wp-cli
    When I run `vendor/bin/wp option get blogname`
    Then STDOUT should contain:
      """
      WP CLI Site with both WordPress and wp-cli as Composer dependencies
      """

  @require-php-5.4
  Scenario: Composer stack with both WordPress and wp-cli as dependencies (web)
    Given a WP installation with Composer
    And a dependency on current wp-cli
    And a PHP built-in web server to serve 'wordpress'
    Then the HTTP status code should be 200

  Scenario: Composer stack with both WordPress and wp-cli as dependencies and a custom vendor directory
    Given a WP installation with Composer and a custom vendor directory 'vendor-custom'
    And a dependency on current wp-cli
    Then the vendor-custom/autoload_commands.php file should exist
    Then the vendor-custom/autoload_framework.php file should exist
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

    When I run `wp config create {CORE_CONFIG_SETTINGS}`
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

    When I try `wp cache add foo bar`
    Then STDERR should contain:
      """
      Error: Site 'invalid.com' not found.
      """
    And the return code should be 1

    When I run `wp cache flush --url=invalid.com`
    Then STDOUT should contain:
      """
      Success:
      """
    And the return code should be 0

  @require-wp-4.0
  Scenario: Run search-replace on ms_site_not_found
    Given a WP multisite installation
    And a wp-cli.yml file:
      """
      url: invalid.com
      """

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
