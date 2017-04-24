Feature: Bootstrap WP-CLI

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

  @broken
  Scenario: Composer stack with override requirement after WP-CLI
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
              "type": "vcs",
              "url": "https://github.com/wp-cli/spyc"
          },
          {
            "type": "path",
            "url": "./cli-command-override"
          }
        ],
        "require": {
          "wp-cli/wp-cli": "dev-3850-refactor-loading-order as 1.2.0-alpha",
          "wp-cli/cli-override": "*"
        }
      }
      """
    And a cli-command-override/cli.php file:
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
    And a cli-command-override/src/CLI_Command.php file:
      """
      <?php
      class CLI_Command extends WP_CLI_Command {
        public function version() {
          WP_CLI::success( "WP-Override-CLI" );
          exit;
        }
      }
      """
    And a cli-command-override/composer.json file:
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
    And I run `composer install --no-interaction`

    When I run `vendor/bin/wp cli version`
    Then STDOUT should not contain:
      """
      Success: WP-Override-CLI
      """

  @broken
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
              "type": "vcs",
              "url": "https://github.com/wp-cli/spyc"
          },
          {
            "type": "path",
            "url": "./cli-command-override"
          }
        ],
        "require": {
          "wp-cli/cli-override": "*",
          "wp-cli/wp-cli": "dev-3850-refactor-loading-order as 1.2.0-alpha"
        }
      }
      """
    And a cli-command-override/cli.php file:
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
    And a cli-command-override/src/CLI_Command.php file:
      """
      <?php
      class CLI_Command extends WP_CLI_Command {
        public function version() {
          WP_CLI::success( "WP-Override-CLI" );
        }
      }
      """
    And a cli-command-override/composer.json file:
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
    And I run `composer install --no-interaction`

    When I run `vendor/bin/wp cli version`
    Then STDOUT should contain:
      """
      Success: WP-Override-CLI
      """

  Scenario: Override command bundled with current source

    Given an empty directory
    And a cli-command-override/cli.php file:
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
    And a cli-command-override/src/CLI_Command.php file:
      """
      <?php
      class CLI_Command extends WP_CLI_Command {
        public function version() {
          WP_CLI::success( "WP-Override-CLI" );
        }
      }
      """
    And a cli-command-override/composer.json file:
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
    And I run `composer install --working-dir={RUN_DIR}/cli-command-override --no-interaction`

    When I run `wp cli version`
      Then STDOUT should contain:
        """
        WP-CLI
        """

    When I run `wp --require=cli-command-override/cli.php cli version`
      Then STDOUT should contain:
        """
        WP-Override-CLI
        """

  Scenario: Override command bundled with freshly built PHAR

    Given an empty directory
    And a new Phar with the same version
    And a cli-command-override/cli.php file:
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
    And a cli-command-override/src/CLI_Command.php file:
      """
      <?php
      class CLI_Command extends WP_CLI_Command {
        public function version() {
          WP_CLI::success( "WP-Override-CLI" );
        }
      }
      """
    And a cli-command-override/composer.json file:
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
    And I run `composer install --working-dir={RUN_DIR}/cli-command-override --no-interaction`

    When I run `{PHAR_PATH} cli version`
      Then STDOUT should contain:
        """
        WP-CLI
        """

    When I run `{PHAR_PATH} --require=cli-command-override/cli.php cli version`
      Then STDOUT should contain:
        """
        WP-Override-CLI
        """
