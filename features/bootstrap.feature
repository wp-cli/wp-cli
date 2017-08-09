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
    And I run `composer install --no-interaction`

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
    And I run `composer install --no-interaction`

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
    And I run `composer install --working-dir={RUN_DIR}/cli-override-command --no-interaction`

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
    And I run `composer install --working-dir={RUN_DIR}/cli-override-command --no-interaction`

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
    Given a WP install with Composer
    And a dependency on current wp-cli
    When I run `vendor/bin/wp option get blogname`
    Then STDOUT should contain:
      """
      WP CLI Site with both WordPress and wp-cli as Composer dependencies
      """

  @require-php-5.4
  Scenario: Composer stack with both WordPress and wp-cli as dependencies (web)
    Given a WP install with Composer
    And a dependency on current wp-cli
    And a PHP built-in web server
    Then the HTTP status code should be 200

  Scenario: Composer stack with both WordPress and wp-cli as dependencies and a custom vendor directory
    Given a WP install with Composer and a custom vendor directory 'vendor-custom'
    And a dependency on current wp-cli
    Then the vendor-custom/autoload_commands.php file should exist
    Then the vendor-custom/autoload_framework.php file should exist
    When I run `vendor-custom/bin/wp option get blogname`
    Then STDOUT should contain:
      """
      WP CLI Site with both WordPress and wp-cli as Composer dependencies
      """
