Feature: Evaluating PHP code and files.

  Scenario: Basics
    Given a WP install

    When I run `wp eval 'var_dump(defined("WP_CONTENT_DIR"));'`
    Then STDOUT should be:
      """
      bool(true)
      """

    Given a script.php file:
      """
      <?php
      WP_CLI::line( implode( ' ', $args ) );
      """

    When I run `wp eval-file script.php foo bar`
    Then STDOUT should be:
      """
      foo bar
      """
