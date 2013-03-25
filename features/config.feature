Feature: Have a config file

  Scenario: No config file
    Given a WP install
      """
      """

    When I run `wp --info`
    Then it should run without errors
    And STDOUT should not contain:
      """
      wp-cli.yml
      """

  Scenario: Config file in WP Root
    Given a WP install
    And a wp-cli.yml file:
      """
      """

    When I run `wp --info`
    Then it should run without errors
    And STDOUT should contain:
      """
      wp-cli.yml
      """

  Scenario: WP in a subdirectory
    Given a WP install in 'core'
    And a wp-cli.yml file:
      """
      path: core
      """

    When I run `wp core version`
    Then it should run without errors
    And STDOUT should not be empty
