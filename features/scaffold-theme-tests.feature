Feature: Scaffold theme unit tests

  Background:
    Given a WP install
    And I run `wp theme install p2`
    And I run `wp scaffold child-theme p2child --parent_theme=p2`

    When I run `wp theme path`
    Then save STDOUT as {THEME_DIR}

  Scenario: Scaffold theme tests
    When I run `wp scaffold theme-tests p2child`
    Then STDOUT should not be empty
    And the {THEME_DIR}/p2child/tests directory should contain:
      """
      bootstrap.php
      test-sample.php
      """
    And the {THEME_DIR}/p2child/tests/bootstrap.php file should contain:
      """
      register_theme_directory( dirname( $theme_dir ) );
      """
    And the {THEME_DIR}/p2child/tests/bootstrap.php file should contain:
      """
      * @package P2child
      """
    And the {THEME_DIR}/p2child/tests/test-sample.php file should contain:
      """
      * @package P2child
      """
    And the {THEME_DIR}/p2child/bin directory should contain:
      """
      install-wp-tests.sh
      """
    And the {THEME_DIR}/p2child/phpunit.xml.dist file should exist
    And the {THEME_DIR}/p2child/phpcs.ruleset.xml file should exist
    And the {THEME_DIR}/p2child/circle.yml file should not exist
    And the {THEME_DIR}/p2child/.gitlab-ci.yml file should not exist
    And the {THEME_DIR}/p2child/.travis.yml file should contain:
      """
      script:
        - phpcs --standard=phpcs.ruleset.xml $(find . -name '*.php')
        - phpunit
      """

    When I run `wp eval "if ( is_executable( '{THEME_DIR}/p2child/bin/install-wp-tests.sh' ) ) { echo 'executable'; } else { exit( 1 ); }"`
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
    When I run `wp scaffold theme-tests p2child --ci=circle`
    Then STDOUT should not be empty
    And the {THEME_DIR}/p2child/.travis.yml file should not exist
    And the {THEME_DIR}/p2child/circle.yml file should contain:
      """
      version: 5.6.22
      """

  Scenario: Scaffold theme tests with Gitlab as the provider
    When I run `wp scaffold theme-tests p2child --ci=gitlab`
    Then STDOUT should not be empty
    And the {THEME_DIR}/p2child/.travis.yml file should not exist
    And the {THEME_DIR}/p2child/.gitlab-ci.yml file should contain:
      """
      MYSQL_DATABASE
      """

  Scenario: Scaffold theme tests with invalid slug

    When I try `wp scaffold theme-tests .`
    Then STDERR should contain:
      """
      Error: Invalid theme slug specified.
      """

    When I try `wp scaffold theme-tests ../`
    Then STDERR should contain:
      """
      Error: Invalid theme slug specified.
      """

