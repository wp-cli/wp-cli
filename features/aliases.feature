Feature: Create shortcuts to specific WordPress installs

  Scenario: Alias for a path to a specific WP install
    Given a WP install in 'testdir'
    And a wp-cli.yml file:
      """
      @testdir:
        path: testdir
      """

    When I try `wp core is-installed`
    Then STDERR should contain:
      """
      Error: This does not seem to be a WordPress install.
      """
    And the return code should be 1

    When I run `wp @testdir core is-installed`
    Then the return code should be 0

  Scenario: Error when invalid alias provided
    Given an empty directory

    When I try `wp @test option get home`
    Then STDERR should be:
      """
      Error: Alias '@test' not found.
      """
