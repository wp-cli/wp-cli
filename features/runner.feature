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

    When I try `wp network option`
    Then STDERR should contain:
      """
      Error: 'option' is not a registered subcommand of 'network'. See 'wp help network' for available subcommands.
      Did you mean 'meta'?
      """
    And the return code should be 1

  @require-wp-4.4
  Scenario: Multisite url validation displays informative error message
    Given a WP multisite installation

    And I run `wp site create --slug=first --porcelain`
    And save STDOUT as {FIRST_SITE_ID}

    When I run `wp option get home --url=example.com/first`
    Then STDOUT should contain:
      """
      https://example.com/first
      """

    When I try `wp option get home --url=example.com/second`
    Then STDOUT should not contain:
      """
      https://example.com/second
      """
    Then STDERR should contain:
      """
      Site 'example.com/second' not found. Verify `--url=<url>` matches an existing site.
      """


