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

  Scenario: defining abspath before WP-CLI is loaded
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
          define( 'ABSPATH', '/wcrtm/' );
      }
      """

    When I try `wp no-such-command --debug`
    Then STDERR should not contain:
      """
      Constant ABSPATH already defined in
      """
    And STDERR should contain:
      """
      ABSPATH defined: /wcrtm/
      """

    When I try `wp no-such-command --path=/foo --debug`
    Then STDERR should contain:
      """
      The --path parameter cannot be used when ABSPATH is already defined elsewhere
      """
