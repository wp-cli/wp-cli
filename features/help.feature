Feature: Get help about WP-CLI commands

  Scenario: Help for internal commands
    Given an empty directory

    When I run `wp help`
    Then STDOUT should contain:
      """
        Run 'wp help <command>' to get more information on a specific command.

      """
    And STDERR should be empty

    When I run `wp help core`
    Then STDOUT should contain:
      """
        wp core
      """
    And STDERR should be empty

    When I run `wp help core download`
    Then STDOUT should contain:
      """
        wp core download
      """
    And STDERR should be empty

    When I run `wp help help`
    Then STDOUT should contain:
      """
        wp help
      """
    And STDERR should be empty

    When I run `wp help help`
    Then STDOUT should contain:
      """
      GLOBAL PARAMETERS
      """
    And STDERR should be empty

    When I run `wp post list --post_type=post --posts_per_page=5 --help`
    Then STDOUT should contain:
      """
      wp post list
      """
    And STDERR should be empty

    When I run `wp post list --post_type=post --posts_per_page=5 --help --prompt`
    Then STDOUT should contain:
      """
      wp post list
      """
    And STDERR should be empty

  Scenario: Include when the command is run if a non-standard hook.
    Given an empty directory

    When I run `COLUMNS=80 wp help db`
    Then STDOUT should contain:
      """
        Unless overridden, these commands run on the 'after_wp_config_load' hook,
        after wp-config.php has been loaded into scope.
      """

    When I run `COLUMNS=150 wp help db check`
    Then STDOUT should contain:
      """
      This command runs on the 'after_wp_config_load' hook, after wp-config.php has been loaded into scope.
      """

    When I run `COLUMNS=150 wp help db size`
    Then STDOUT should not contain:
      """
      This command runs on the
      """

  Scenario: Hide Global parameters when requested
    Given an empty directory

    When I run `wp help`
    Then STDOUT should contain:
      """
      GLOBAL PARAMETERS
      """

    And STDOUT should contain:
      """
      --path
      """

    And STDOUT should contain:
      """
      Path to the WordPress files.
      """

    When I run `WP_CLI_SUPPRESS_GLOBAL_PARAMS=true wp help`
    Then STDOUT should not contain:
      """
      GLOBAL PARAMETERS
      """

    And STDOUT should not contain:
      """
      --path
      """
    And STDOUT should not contain:
      """
      Path to the WordPress files.
      """

    When I run `WP_CLI_SUPPRESS_GLOBAL_PARAMS=false wp help`
    Then STDOUT should contain:
      """
      GLOBAL PARAMETERS
      """

    And STDOUT should contain:
      """
      --path
      """
    And STDOUT should contain:
      """
      Path to the WordPress files.
      """

  # Prior to WP 4.3 widgets & others used PHP 4 style constructors and prior to WP 3.9 wpdb used the mysql extension which can all lead (depending on PHP version) to PHP Deprecated notices.
  @require-wp-4.3
  Scenario: Help for internal commands with WP
    Given a WP installation

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help`
    Then STDOUT should contain:
      """
        Run 'wp help <command>' to get more information on a specific command.

      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help core`
    Then STDOUT should contain:
      """
        wp core
      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help core download`
    Then STDOUT should contain:
      """
        wp core download
      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help help`
    Then STDOUT should contain:
      """
        wp help
      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help help`
    Then STDOUT should contain:
      """
      GLOBAL PARAMETERS
      """

  @require-php-7.0
  Scenario: Help when WordPress is downloaded but not installed
    Given an empty directory

    When I run `wp core download`
    And I run `wp help config create`
    Then STDOUT should contain:
      """
      wp config create
      """
    And STDERR should be empty

    When I run `wp config create {CORE_CONFIG_SETTINGS} --skip-check`
    And I run `wp help core install`
    Then STDOUT should contain:
      """
      wp core install
      """
    And STDERR should be empty

    When I run `wp help core is-installed`
    Then STDOUT should contain:
      """
      wp core is-installed
      """
    And STDERR should be empty

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help core`
    Then STDOUT should contain:
      """
      wp core
      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help config`
    Then STDOUT should contain:
      """
      wp config
      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help db`
    Then STDOUT should contain:
      """
      wp db
      """

    When I try `wp help non-existent-command`
    Then the return code should be 1
    And STDERR should contain:
      """
      Error establishing a database connection
      """
    And STDERR should contain:
      """
      Error: 'non-existent-command' is not a registered wp command. See 'wp help' for available commands.
      """
    And STDOUT should be empty

  Scenario: Help for nonexistent commands
    Given a WP installation

    When I try `wp help non-existent-command`
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: 'non-existent-command' is not a registered wp command. See 'wp help' for available commands.
      """
    And STDOUT should be empty

    When I try `wp help non-existent-command --path=/nowhere`
    Then the return code should be 1
    And STDERR should contain:
      """
      Warning: No WordPress installation found. If the command 'non-existent-command' is in a plugin or theme, pass --path=`path/to/wordpress`.
      Error: 'non-existent-command' is not a registered wp command. See 'wp help' for available commands.
      """
    And STDOUT should be empty

    When I try `wp help non-existent-command non-existent-subcommand`
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: 'non-existent-command' is not a registered wp command. See 'wp help' for available commands.
      """
    And STDOUT should be empty

    When I try `wp help non-existent-command non-existent-subcommand --path=/nowhere`
    Then the return code should be 1
    And STDERR should contain:
      """
      Warning: No WordPress installation found. If the command 'non-existent-command non-existent-subcommand' is in a plugin or theme, pass --path=`path/to/wordpress`.
      Error: 'non-existent-command' is not a registered wp command. See 'wp help' for available commands.
      """
    And STDOUT should be empty

  Scenario: Help for nonexistent commands without a WP installation
    Given an empty directory

    When I try `wp help non-existent-command`
    Then the return code should be 1
    And STDERR should contain:
      """
      Warning: No WordPress installation found. If the command 'non-existent-command' is in a plugin or theme, pass --path=`path/to/wordpress`.
      Error: 'non-existent-command' is not a registered wp command. See 'wp help' for available commands.
      """
    And STDOUT should be empty

  Scenario: Help for specially treated commands with nonexistent subcommands
    Given a WP installation

    When I try `wp help config non-existent-subcommand`
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: 'non-existent-subcommand' is not a registered subcommand of 'config'. See 'wp help config' for available subcommands.
      """
    And STDOUT should be empty

    When I try `wp help config non-existent-subcommand --path=/nowhere`
    Then the return code should be 1
    And STDERR should contain:
      """
      Warning: No WordPress installation found. If the command 'config non-existent-subcommand' is in a plugin or theme, pass --path=`path/to/wordpress`.
      Error: 'non-existent-subcommand' is not a registered subcommand of 'config'. See 'wp help config' for available subcommands.
      """
    And STDOUT should be empty

    When I try `wp help core non-existent-subcommand`
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: 'non-existent-subcommand' is not a registered subcommand of 'core'. See 'wp help core' for available subcommands.
      """
    And STDOUT should be empty

    When I try `wp help core non-existent-subcommand --path=/nowhere`
    Then the return code should be 1
    And STDERR should contain:
      """
      Warning: No WordPress installation found. If the command 'core non-existent-subcommand' is in a plugin or theme, pass --path=`path/to/wordpress`.
      Error: 'non-existent-subcommand' is not a registered subcommand of 'core'. See 'wp help core' for available subcommands.
      """
    And STDOUT should be empty

    When I try `wp help db non-existent-subcommand`
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: 'non-existent-subcommand' is not a registered subcommand of 'db'. See 'wp help db' for available subcommands.
      """
    And STDOUT should be empty

    When I try `wp help db non-existent-subcommand --path=/nowhere`
    Then the return code should be 1
    And STDERR should contain:
      """
      Warning: No WordPress installation found. If the command 'db non-existent-subcommand' is in a plugin or theme, pass --path=`path/to/wordpress`.
      Error: 'non-existent-subcommand' is not a registered subcommand of 'db'. See 'wp help db' for available subcommands.
      """
    And STDOUT should be empty

  Scenario: Suggestions for command typos in help
    Given an empty directory

    When I try `wp help confi`
    Then the return code should be 1
    And STDERR should be:
      """
      Warning: No WordPress installation found. If the command 'confi' is in a plugin or theme, pass --path=`path/to/wordpress`.
      Error: 'confi' is not a registered wp command. See 'wp help' for available commands.
      Did you mean 'config'?
      """
    And STDOUT should be empty

    When I try `wp help cor`
    Then the return code should be 1
    And STDERR should be:
      """
      Warning: No WordPress installation found. If the command 'cor' is in a plugin or theme, pass --path=`path/to/wordpress`.
      Error: 'cor' is not a registered wp command. See 'wp help' for available commands.
      Did you mean 'core'?
      """
    And STDOUT should be empty

    When I try `wp help d`
    Then the return code should be 1
    And STDERR should be:
      """
      Warning: No WordPress installation found. If the command 'd' is in a plugin or theme, pass --path=`path/to/wordpress`.
      Error: 'd' is not a registered wp command. See 'wp help' for available commands.
      Did you mean 'db'?
      """
    And STDOUT should be empty

    When I try `wp help packag`
    Then the return code should be 1
    And STDERR should be:
      """
      Warning: No WordPress installation found. If the command 'packag' is in a plugin or theme, pass --path=`path/to/wordpress`.
      Error: 'packag' is not a registered wp command. See 'wp help' for available commands.
      Did you mean 'package'?
      """
    And STDOUT should be empty

  Scenario: Suggestions for subcommand typos in help of specially treated commands
    Given an empty directory

    When I try `wp help config creat`
    Then the return code should be 1
    And STDERR should be:
      """
      Warning: No WordPress installation found. If the command 'config creat' is in a plugin or theme, pass --path=`path/to/wordpress`.
      Error: 'creat' is not a registered subcommand of 'config'. See 'wp help config' for available subcommands.
      Did you mean 'create'?
      """
    And STDOUT should be empty

    When I try `wp help core versio`
    Then the return code should be 1
    And STDERR should be:
      """
      Warning: No WordPress installation found. If the command 'core versio' is in a plugin or theme, pass --path=`path/to/wordpress`.
      Error: 'versio' is not a registered subcommand of 'core'. See 'wp help core' for available subcommands.
      Did you mean 'version'?
      """
    And STDOUT should be empty

    When I try `wp help db chec`
    Then the return code should be 1
    And STDERR should be:
      """
      Warning: No WordPress installation found. If the command 'db chec' is in a plugin or theme, pass --path=`path/to/wordpress`.
      Error: 'chec' is not a registered subcommand of 'db'. See 'wp help db' for available subcommands.
      Did you mean 'check'?
      """
    And STDOUT should be empty

  Scenario: No WordPress installation warning or suggestions for disabled commands
    Given an empty directory
    And a wp-cli.yml file:
      """
      disabled_commands:
        db
      """

    When I try `wp help db`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: The 'db' command has been disabled from the config file.
      """
    And STDOUT should be empty

    When I try `wp help db chec`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: The 'db' command has been disabled from the config file.
      """
    And STDOUT should be empty

  Scenario: Help for third-party commands
    Given a WP installation
    And a wp-content/plugins/test-cli/command.php file:
      """
      <?php
      // Plugin Name: Test CLI Help

      class Test_Help extends WP_CLI_Command {
        /**
         * A dummy command.
         */
        function __invoke() {}
      }

      WP_CLI::add_command( 'test-help', 'Test_Help' );
      """
    And I run `wp plugin activate test-cli`

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help`
    Then STDOUT should contain:
      """
      A dummy command.
      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help test-help`
    Then STDOUT should contain:
      """
      wp test-help
      """

  Scenario: Help for incomplete commands
    Given an empty directory

    When I run `wp core`
    Then STDOUT should contain:
      """
      usage: wp core
      """

  Scenario: Help for commands with magic methods
    Given a WP installation
    And a wp-content/plugins/test-cli/command.php file:
      """
      <?php
      // Plugin Name: Test CLI Help

      class Test_Magic_Methods extends WP_CLI_Command {
        /**
         * A dummy command.
         *
         * @subcommand my-command
         */
        function my_command() {}

        /**
         * Magic methods should not appear as commands
         */
        function __construct() {}
        function __destruct() {}
        function __call( $name, $arguments ) {}
        function __get( $key ) {}
        function __set( $key, $value ) {}
        function __isset( $key ) {}
        function __unset( $key ) {}
        function __sleep() {}
        function __wakeup() {}
        function __toString() {}
        static function __set_state( $properties ) {}
        function __clone() {}
        function __debugInfo() {}
      }

      WP_CLI::add_command( 'test-magic-methods', 'Test_Magic_Methods' );
      """
    And I run `wp plugin activate test-cli`

    When I run `wp test-magic-methods`
    Then STDOUT should contain:
      """
      usage: wp test-magic-methods my-command
      """
    And STDOUT should not contain:
      """
      __destruct
      """

  Scenario: Help for commands loaded into existing namespaces
    Given a WP installation
    And a wp-content/plugins/test-cli/command.php file:
      """
      <?php
      // Plugin Name: Test CLI Extra Site Command

      class Test_CLI_Extra_Site_Command extends WP_CLI_Command {

        /**
         * A dummy command.
         *
         * @subcommand my-command
         */
        function my_command() {}

      }

      WP_CLI::add_command( 'site test-extra', 'Test_CLI_Extra_Site_Command' );
      """
    And I run `wp plugin activate test-cli`

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help site`
    Then STDOUT should contain:
      """
      test-extra
      """

    Given a wp-content/plugins/test-cli/command.php file:
      """
      <?php
      // Plugin Name: Test CLI Extra Command

      class Test_CLI_Extra_Command extends WP_CLI_Command {

        /**
         * A dummy command.
         *
         * @subcommand my-command
         */
        function my_command() {}

      }

      WP_CLI::add_command( 'config test-extra-config', 'Test_CLI_Extra_Command' );
      WP_CLI::add_command( 'core test-extra-core', 'Test_CLI_Extra_Command' );
      WP_CLI::add_command( 'db test-extra-db', 'Test_CLI_Extra_Command' );
      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help config`
    Then STDOUT should contain:
      """
      test-extra-config
      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help core`
    Then STDOUT should contain:
      """
      test-extra-core
      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help db`
    Then STDOUT should contain:
      """
      test-extra-db
      """

  Scenario: Help renders global parameters correctly
    Given a WP installation

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help core`
    Then STDOUT should contain:
      """
      GLOBAL PARAMETERS
      """
    And STDOUT should not contain:
      """
      ## GLOBAL PARAMETERS
      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help option get`
    Then STDOUT should contain:
      """
      GLOBAL PARAMETERS
      """
    And STDOUT should not contain:
      """
      ## GLOBAL PARAMETERS
      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `wp help option`
    Then STDOUT should contain:
      """
      GLOBAL PARAMETERS
      """
    And STDOUT should not contain:
      """
      ## GLOBAL PARAMETERS
      """

  Scenario: Display alias in man page
    Given a WP installation

    When I run `wp help plugin update`
    Then STDOUT should contain:
      """
      ALIAS

        upgrade
      """

    When I run `wp help plugin install`
    Then STDOUT should not contain:
      """
      ALIAS
      """

  Scenario: Help for commands should wordwrap well
    Given a WP installation
    And a wp-content/plugins/test-cli/command.php file:
      """
      <?php
      // Plugin Name: Test CLI Help

      class Test_Wordwrap extends WP_CLI_Command {
        /**
         * 123456789 123456789 123456789 123456789 123456789 123456789 123456789 12345678
         *
         * ## OPTIONS
         *
         * [--skip-delete]
         * : Skip deletion of the original thumbnails. If your thumbnails are linked from sources outside your control, it's likely best to leave them around. Defaults to false.
         *
         * [--eighty=<four initial spaces>]
         * : 123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456
         *
         * [--eighty-one=<four initial spaces>]
         * : 123456789 123456789 123456789 123456789 123456789 123456789 123456789 1234567
         *
         * [--forty=<four initial spaces>]
         * : 123456789 123456789 123456789 123456
         *
         * [--forty-one=<four initial spaces>]
         * : 123456789 123456789 123456789 1234567
         *
         * ## EXAMPLES
         *
         *     # Re-generate only the thumbnails of "large" image size for all images.
         *     $ wp media regenerate --image_size=large
         *     Do you really want to regenerate the "large" image size for all images? [y/n] y
         *     Found 3 images to regenerate.
         *     1/3 Regenerated "large" thumbnail for "Yoogest Image Ever, Really" (ID 9999).
         *     2/3 No "large" thumbnail regeneration needed for "Snowflake" (ID 9998).
         *     3/3 Regenerated "large" thumbnail for "Even Yooger than the Yoogest Image Ever, Really" (ID 9997).
         *     Success: Regenerated 3 of 3 images.
         *
         *     # 6 initial spaces + 74 = 80; 6 + 75 = 81
         *     # 123456789 123456789 123456789 123456789 123456789 123456789 123456789 1234
         *     # 123456789 123456789 123456789 123456789 123456789 123456789 123456789 12345
         *
         *     # 6 initial spaces + 34 = 40; 6 + 35 = 41
         *     # 123456789 123456789 123456789 1234
         *     # 123456789 123456789 123456789 12345
         *
         */
        function my_command() {}
      }

      WP_CLI::add_command( 'test-wordwrap', 'Test_Wordwrap' );
      """
    And I run `wp plugin activate test-cli`

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `COLUMNS=80 wp help test-wordwrap my_command`
    Then STDOUT should contain:
      """
        123456789 123456789 123456789 123456789 123456789 123456789 123456789 12345678

      """
    And STDOUT should contain:
      """
        [--skip-delete]
          Skip deletion of the original thumbnails. If your thumbnails are linked from
          sources outside your control, it's likely best to leave them around.
          Defaults to false.

      """
    And STDOUT should contain:
      """
        [--eighty=<four initial spaces>]
          123456789 123456789 123456789 123456789 123456789 123456789 123456789 123456

      """
    And STDOUT should contain:
      """
        [--eighty-one=<four initial spaces>]
          123456789 123456789 123456789 123456789 123456789 123456789 123456789
          1234567

      """
    And STDOUT should contain:
      """
          # Re-generate only the thumbnails of "large" image size for all images.
          $ wp media regenerate --image_size=large
          Do you really want to regenerate the "large" image size for all images?
          [y/n] y
          Found 3 images to regenerate.
          1/3 Regenerated "large" thumbnail for "Yoogest Image Ever, Really" (ID
          9999).
          2/3 No "large" thumbnail regeneration needed for "Snowflake" (ID 9998).
          3/3 Regenerated "large" thumbnail for "Even Yooger than the Yoogest Image
          Ever, Really" (ID 9997).
          Success: Regenerated 3 of 3 images.

      """
    And STDOUT should contain:
      """
          # 123456789 123456789 123456789 123456789 123456789 123456789 123456789 1234
          # 123456789 123456789 123456789 123456789 123456789 123456789 123456789
          12345
      """
    And STDOUT should contain:
      """
        --url=<url>
            Pretend request came from given URL. In multisite, this argument is how
            the target site is specified.

      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `COLUMNS=80 wp help test-wordwrap my_command | awk '{print length, $0}' | sort -nr | head -1 | cut -f1 -d" "`
    Then STDOUT should be:
      """
      80
      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `TERM=vt100 COLUMNS=40 wp help test-wordwrap my_command`
    Then STDOUT should contain:
      """
        123456789 123456789 123456789
        123456789 123456789 123456789
        123456789 12345678

      """
    And STDOUT should contain:
      """
        [--skip-delete]
          Skip deletion of the original
          thumbnails. If your thumbnails are
          linked from sources outside your
          control, it's likely best to leave
          them around. Defaults to false.

      """
    And STDOUT should contain:
      """
        [--forty=<four initial spaces>]
          123456789 123456789 123456789 123456

      """
    And STDOUT should contain:
      """
        [--forty-one=<four initial spaces>]
          123456789 123456789 123456789
          1234567

      """
    And STDOUT should contain:
      """
          # Re-generate only the thumbnails of
          "large" image size for all images.
          $ wp media regenerate
          --image_size=large
          Do you really want to regenerate the
          "large" image size for all images?
          [y/n] y
          Found 3 images to regenerate.
          1/3 Regenerated "large" thumbnail
          for "Yoogest Image Ever, Really" (ID
          9999).
          2/3 No "large" thumbnail
          regeneration needed for "Snowflake"
          (ID 9998).
          3/3 Regenerated "large" thumbnail
          for "Even Yooger than the Yoogest
          Image Ever, Really" (ID 9997).
          Success: Regenerated 3 of 3 images.

      """
    And STDOUT should contain:
      """
          # 123456789 123456789 123456789 1234
          # 123456789 123456789 123456789
          12345
      """
    And STDOUT should contain:
      """
        --url=<url>
            Pretend request came from given
            URL. In multisite, this argument
            is how the target site is
            specified.

      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `TERM=vt100 COLUMNS=40 wp help test-wordwrap my_command | sed '/\-\-ssh/d' | awk '{print length, $0}' | sort -nr | head -1 | cut -f1 -d" "`
    Then STDOUT should be:
      """
      40
      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `TERM=vt100 COLUMNS=1000 wp help test-wordwrap my_command`
    Then STDOUT should contain:
      """
        [--skip-delete]
          Skip deletion of the original thumbnails. If your thumbnails are linked from sources outside your control, it's likely best to leave them around. Defaults to false.

      """
    And STDOUT should contain:
      """
          # Re-generate only the thumbnails of "large" image size for all images.
          $ wp media regenerate --image_size=large
          Do you really want to regenerate the "large" image size for all images? [y/n] y
          Found 3 images to regenerate.
          1/3 Regenerated "large" thumbnail for "Yoogest Image Ever, Really" (ID 9999).
          2/3 No "large" thumbnail regeneration needed for "Snowflake" (ID 9998).
          3/3 Regenerated "large" thumbnail for "Even Yooger than the Yoogest Image Ever, Really" (ID 9997).
          Success: Regenerated 3 of 3 images.

      """
    And STDOUT should contain:
      """
        --url=<url>
            Pretend request came from given URL. In multisite, this argument is how the target site is specified.

      """

  Scenario: Help for commands with subcommands should wordwrap well
    Given a WP installation
    And a wp-content/plugins/test-cli/command.php file:
      """
      <?php
      // Plugin Name: Test CLI Help

      class Test_Wordwrap extends WP_CLI_Command {
        /**
         * Generate PHP code for registering a custom post type.
         *
         * @subcommand post-type
         *
         * @alias      cpt
         */
        public function post_type( $args, $assoc_args ) {}

        /**
         * Generate starter code for a theme based on _s.
         *
         * See the [Underscores website](http://underscores.me/) for more details.
         */
        public function _s( $args, $assoc_args ) {}

        /**
         * Generate GitHub configuration files for your command.
         *
         * @when       before_wp_load
         * @subcommand package-github
         */
        public function package_github( $args, $assoc_args ) {}

        /**
         * Generate files needed for writing Behat tests for your command.
         *
         * @when       before_wp_load
         * @subcommand package-tests
         */
        public function package_tests( $args, $assoc_args ) {}

        /**
         * Generate files needed for running PHPUnit tests in a plugin.
         *
         * @subcommand plugin-tests
         */
        public function plugin_tests( $args, $assoc_args ) {}

        /**
         * Generate files needed for running PHPUnit tests in a theme.
         *
         * @subcommand theme-tests
         */
        public function theme_tests( $args, $assoc_args ) {}

        /**
         * 2 chars initial + 20 padded command + 58 these = 80 chars.
         *
         * @subcommand eighty
         */
        public function eighty( $args, $assoc_args ) {}

        /**
         * 2 chars initial + 20 padded command + 59 these = 81 chars..
         *
         * @subcommand eighty-one
         */
        public function eighty_one( $args, $assoc_args ) {}

        /**
         * A very long description a very longgggggggggg description a very longgggg description a very long description a very longgggggggggggggggg description a very long description a very long description a very long description a very longgg description.
         *
         * @subcommand very-long
         */
        public function very_long( $args, $assoc_args ) {}
      }

      WP_CLI::add_command( 'test-wordwrap', 'Test_Wordwrap' );
      """
    And I run `wp plugin activate test-cli`

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `TERM=vt100 COLUMNS=80 wp help test-wordwrap`
    Then STDOUT should contain:
      """
      SUBCOMMANDS

        _s                  Generate starter code for a theme based on _s.
        eighty              2 chars initial + 20 padded command + 58 these = 80 chars.
        eighty-one          2 chars initial + 20 padded command + 59 these = 81
                            chars..
        package-github      Generate GitHub configuration files for your command.
        package-tests       Generate files needed for writing Behat tests for your
                            command.
        plugin-tests        Generate files needed for running PHPUnit tests in a
                            plugin.
        post-type           Generate PHP code for registering a custom post type.
        theme-tests         Generate files needed for running PHPUnit tests in a
                            theme.
        very-long           A very long description a very longgggggggggg description
                            a very longgggg description a very long description a very
                            longgggggggggggggggg description a very long description a
                            very long description a very long description a very
                            longgg description.

      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `TERM=vt100 COLUMNS=80 wp help test-wordwrap | awk '{print length, $0}' | sort -nr | head -1 | cut -f1 -d" "`
    Then STDOUT should be:
      """
      80
      """

  Scenario: Long description for top-level command which has reference link display well
    Given a WP installation
    And a command.php file:
      """
      <?php

      if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
          return;
      }

      class WP_CLI_Foo_Bar_Command extends WP_CLI_Command {
          /**
          * A command that has a link in its long description.
          *
          * This is a [reference link](https://wordpress.org/).
          * Also, there is a [second link](http://wp-cli.org/).
          * They should be displayed nicely!
          *
          * @synopsis <constant-name>
          */
          public function __invoke( $args, $assoc_args ) {}
      }

      WP_CLI::add_command( 'reference-link', 'WP_CLI_Foo_Bar_Command' );
      """
    And a wp-cli.yml file:
      """
      require:
        - command.php
      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `TERM=vt100 COLUMNS=80 wp help reference-link`
    Then STDOUT should contain:
      """
        This is a [reference link][1].
        Also, there is a [second link][2].
        They should be displayed nicely!

        ---
        [1] https://wordpress.org/
        [2] http://wp-cli.org/
      """

  Scenario: Very long description for top-level command which has reference link display well
    Given a WP installation
    And a command.php file:
      """
      <?php

      if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
          return;
      }

      class WP_CLI_Foo_Bar_Command extends WP_CLI_Command {
          /**
          * A command that has a link in its long description.
          *
          * This is a [reference link](https://wordpress.org/). Also, there is a [second link](http://wp-cli.org/). They should be displayed nicely! Wow! This is a very, very long description.
          *
          * @synopsis <constant-name>
          */
          public function __invoke( $args, $assoc_args ) {}
      }

      WP_CLI::add_command( 'reference-link', 'WP_CLI_Foo_Bar_Command' );
      """
    And a wp-cli.yml file:
      """
      require:
        - command.php
      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `TERM=vt100 COLUMNS=80 wp help reference-link`
    Then STDOUT should contain:
      """
        This is a [reference link][1]. Also, there is a [second link][2]. They should
        be displayed nicely! Wow! This is a very, very long description.

        ---
        [1] https://wordpress.org/
        [2] http://wp-cli.org/
      """

    # TODO: Throwing deprecations with PHP 8.1+ and WP < 5.9
    When I try `TERM=vt100 COLUMNS=60 wp help reference-link`
    Then STDOUT should contain:
      """
        This is a [reference link][1]. Also, there is a [second
        link][2]. They should be displayed nicely! Wow! This is a
        very, very long description.

        ---
        [1] https://wordpress.org/
        [2] http://wp-cli.org/
      """

  Scenario Outline: Check that proc_open() and proc_close() aren't disabled for help pager
    Given an empty directory
    When I try `{INVOKE_WP_CLI_WITH_PHP_ARGS--ddisable_functions=<func>} help --debug`
    Then STDERR should contain:
      """
      Warning: check_proc_available() failed in pass_through_pager().
      """
    And STDOUT should not be empty
    And the return code should be 0

    Examples:
      | func       |
      | proc_open  |
      | proc_close |
