Feature: Evaluating PHP code and files.

  Scenario: Basics
    Given a WP install

    When I run `wp eval 'var_dump(defined("WP_CONTENT_DIR"));'`
    Then STDOUT should be:
      """
      bool(true)
      """

    Given a script1.php file:
      """
      <?php
      WP_CLI::line("script 1");
      """
    And a script2.php file:
      """
      <?php
      WP_CLI::line("script 2");
      """

    When I run `wp eval-file script1.php script2.php`
    Then STDOUT should be:
      """
      script 1
      script 2
      """
