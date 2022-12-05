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