Feature: Get help about WP-CLI commands

  Scenario: Help for internal commands
    Given an empty directory

    When I run `wp help`
    Then STDOUT should not be empty
    And STDOUT should contain:
      """
        Run 'wp help <command>' to get more information on a specific command.

      """
    And STDERR should be empty

    When I run `wp help core`
    Then STDOUT should not be empty
    And STDERR should be empty

    When I run `wp help core download`
    Then STDOUT should not be empty
    And STDERR should be empty

    When I run `wp help help`
    Then STDOUT should not be empty
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

  Scenario: Help when WordPress is downloaded but not installed
    Given an empty directory

    When I run `wp core download`
    And I run `wp help config create`
    Then STDOUT should contain:
      """
      wp config create
      """

    When I run `wp core config {CORE_CONFIG_SETTINGS}`
    And I run `wp help core install`
    Then STDOUT should contain:
      """
      wp core install
      """

  Scenario: Help for nonexistent commands
    Given a WP install

    When I try `wp help non-existent-command`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: 'non-existent-command' is not a registered wp command.
      """

    When I try `wp help non-existent-command non-existent-subcommand`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: 'non-existent-command non-existent-subcommand' is not a registered wp command.
      """

  Scenario: Help for third-party commands
    Given a WP install
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

    When I run `wp help`
    Then STDOUT should contain:
      """
      A dummy command.
      """
    And STDERR should be empty

    When I run `wp help test-help`
    Then STDOUT should contain:
      """
      wp test-help
      """
    And STDERR should be empty

  Scenario: Help for incomplete commands
    Given an empty directory

    When I run `wp core`
    Then STDOUT should contain:
      """
      usage: wp core
      """

  Scenario: Help for commands with magic methods
    Given a WP install
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
        function __set_state() {}
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
    Given a WP install
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

    When I run `wp help site`
    Then STDOUT should contain:
      """
      test-extra
      """

  Scenario: Help renders global parameters correctly
    Given a WP install

    When I run `wp help import get`
    Then STDOUT should contain:
      """
      GLOBAL PARAMETERS
      """
    And STDOUT should not contain:
      """
      ## GLOBAL PARAMETERS
      """

    When I run `wp help option get`
    Then STDOUT should contain:
      """
      GLOBAL PARAMETERS
      """
    And STDOUT should not contain:
      """
      ## GLOBAL PARAMETERS
      """

    When I run `wp help option`
    Then STDOUT should contain:
      """
      GLOBAL PARAMETERS
      """
    And STDOUT should not contain:
      """
      ## GLOBAL PARAMETERS
      """

  Scenario: Display alias in man page
    Given a WP install

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
    Given a WP install
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

    When I run `wp help test-wordwrap my_command`
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
    And STDERR should be empty

    When I run `wp help test-wordwrap my_command | wc -L`
    Then STDOUT should be:
      """
      80
      """

    When I run `TERM=vt100 COLUMNS=40 wp help test-wordwrap my_command`
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
    And STDERR should be empty

    When I run `TERM=vt100 COLUMNS=40 wp help test-wordwrap my_command | sed '/\-\-ssh/d' | wc -L`
    Then STDOUT should be:
      """
      40
      """

    When I run `TERM=vt100 COLUMNS=1000 wp help test-wordwrap my_command`
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
    And STDERR should be empty

  Scenario: Help for commands with subcommands should wordwrap well
    Given a WP install
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

    When I run `TERM=vt100 COLUMNS=80 wp help test-wordwrap`
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
    And STDERR should be empty

    When I run `TERM=vt100 COLUMNS=80 wp help test-wordwrap | wc -L`
    Then STDOUT should be:
      """
      80
      """
