Feature: Have a config file

  Scenario: No config file
    Given a WP install

    When I run `wp --info`
    Then STDOUT should not contain:
      """
      wp-cli.yml
      """

    When I run `wp core is-installed` from 'wp-content'
    Then STDOUT should be empty

  Scenario: Config file in WP Root
    Given a WP install
    And a wp-cli.yml file:
      """
      """

    When I run `wp --info`
    Then STDOUT should contain:
      """
      wp-cli.yml
      """

    When I run `wp core is-installed`
    Then STDOUT should be empty

  Scenario: WP in a subdirectory
    Given a WP install in 'core'
    And a wp-cli.yml file:
      """
      path: core
      """

    When I run `wp --info`
    Then STDOUT should contain:
      """
      wp-cli.yml
      """

    When I run `wp core is-installed`
    Then STDOUT should be empty

    When I run `wp core is-installed` from 'core/wp-content'
    Then STDOUT should be empty

  Scenario: Nested installs
    Given a WP install
    And a WP install in 'subsite'
    And a wp-cli.yml file:
      """
      """

    When I run `wp --info` from 'subsite'
    Then STDOUT should not contain:
      """
      wp-cli.yml
      """

