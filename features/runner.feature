Feature: Runner WP-CLI

  Scenario: Path argument should be slashed correctly
    When I try `wp no-such-command --path=/foo --debug`
    Then STDERR should contain:
      """
      ABSPATH defined: /foo/
      """

    When I try `wp no-such-command --path=/foo/ --debug`
    Then STDERR should contain:
      """
      ABSPATH defined: /foo/
      """

    When I try `wp no-such-command --path=/foo\\ --debug`
    Then STDERR should contain:
      """
      ABSPATH defined: /foo/
      """

  Scenario: ABSPATH can be defined outside of WP-CLI
    Given an empty directory
    And a wp-cli.yml file:
      """
      require:
        - abspath.php
      """
    And a abspath.php file:
      """
      <?php
      if ( ! defined( 'ABSPATH' ) ) {
          define( 'ABSPATH', '/some_path/' );
      }
      """

    When I try `wp no-such-command --debug`
    Then STDERR should not contain:
      """
      Constant ABSPATH already defined in
      """
    And STDERR should contain:
      """
      ABSPATH defined: /some_path/
      """

    When I try `wp no-such-command --path=/foo --debug`
    Then STDERR should contain:
      """
      The --path parameter cannot be used when ABSPATH is already defined elsewhere
      """

  Scenario: Empty path argument should be handled correctly
    When I try `wp no-such-command --path`
    Then STDERR should contain:
      """
      The --path parameter cannot be empty when provided
      """

    When I try `wp no-such-command --path=`
    Then STDERR should contain:
      """
      The --path parameter cannot be empty when provided
      """

    When I try `wp no-such-command --path= some_path`
    Then STDERR should contain:
      """
      The --path parameter cannot be empty when provided
      """

  Scenario: Suggest 'meta' when 'option' subcommand is run
    Given a WP install
    And a session_no file:
      """
      n
      """
    And a session_yes file:
      """
      y
      """

    When I try `wp network option < session_no`
    Then STDERR should contain:
      """
      Warning: 'option' is not a registered subcommand of 'network'. See 'wp help network' for available subcommands.
      """
    And STDOUT should contain:
      """
      Did you mean 'meta'? [y/n]
      """
    And the return code should be 0

    When I try `wp network option < session_yes`
    Then STDERR should contain:
      """
      Warning: 'option' is not a registered subcommand of 'network'. See 'wp help network' for available subcommands.
      """
    And STDOUT should contain:
      """
      Did you mean 'meta'? [y/n]
      """
    And STDOUT should contain:
      """
      See 'wp help network meta <command>' for more information
      """
    And the return code should be 0

  Scenario: Suggest 'wp term <command>' when an invalid taxonomy command is run
    Given a WP install

    When I try `wp category list`
    Then STDERR should contain:
      """
      Did you mean 'wp term <command>'?
      """
    And the return code should be 1

  Scenario: Suggest 'wp post <command>' when an invalid post type command is run
    Given a WP install

    When I try `wp page create`
    Then STDERR should contain:
      """
      Did you mean 'wp post --post_type=page <command>'?
      """
    And the return code should be 1
