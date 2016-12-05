Feature: Scaffold theme unit tests

  Background:
    Given a WP install
    And I run `wp theme install hexa`
    And I run `wp scaffold child-theme hexachild --parent_theme=hexa`

    When I run `wp theme path`
    Then save STDOUT as {THEME_DIR}

  Scenario: Scaffold theme tests
    When I run `wp scaffold theme-tests hexachild`
    Then STDOUT should not be empty
    And the {THEME_DIR}/hexachild/tests directory should contain:
      """
      bootstrap.php
      test-sample.php
      """
    And the {THEME_DIR}/hexachild/tests/bootstrap.php file should contain:
      """
      register_theme_directory( dirname( $theme_dir ) );
      """
    And the {THEME_DIR}/hexachild/tests/bootstrap.php file should contain:
      """
      * @package Hexachild
      """
    And the {THEME_DIR}/hexachild/tests/test-sample.php file should contain:
      """
      * @package Hexachild
      """
    And the {THEME_DIR}/hexachild/bin directory should contain:
      """
      install-wp-tests.sh
      """
    And the {THEME_DIR}/hexachild/phpunit.xml.dist file should exist
    And the {THEME_DIR}/hexachild/phpcs.ruleset.xml file should exist
    And the {THEME_DIR}/hexachild/circle.yml file should not exist
    And the {THEME_DIR}/hexachild/.gitlab-ci.yml file should not exist
    And the {THEME_DIR}/hexachild/.travis.yml file should contain:
      """
      script:
        - phpcs --standard=phpcs.ruleset.xml $(find . -name '*.php')
        - phpunit
      """

    When I run `wp eval "if ( is_executable( '{THEME_DIR}/hexachild/bin/install-wp-tests.sh' ) ) { echo 'executable'; } else { exit( 1 ); }"`
    Then STDOUT should be:
      """
      executable
      """

  Scenario: Scaffold theme tests invalid theme
    When I try `wp scaffold theme-tests p3child`
    Then STDERR should be:
      """
      Error: Invalid theme slug specified.
      """

  Scenario: Scaffold theme tests with Circle as the provider
    When I run `wp scaffold theme-tests hexachild --ci=circle`
    Then STDOUT should not be empty
    And the {THEME_DIR}/hexachild/.travis.yml file should not exist
    And the {THEME_DIR}/hexachild/circle.yml file should contain:
      """
      version: 5.6.22
      """

  Scenario: Scaffold theme tests with Gitlab as the provider
    When I run `wp scaffold theme-tests hexachild --ci=gitlab`
    Then STDOUT should not be empty
    And the {THEME_DIR}/hexachild/.travis.yml file should not exist
    And the {THEME_DIR}/hexachild/.gitlab-ci.yml file should contain:
      """
      MYSQL_DATABASE
      """
