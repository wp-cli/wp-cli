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

  Scenario: Treat global params as local when included in alias
    Given a WP install in 'testdir'
    And a wp-cli.yml file:
      """
      @testdir:
        path: testdir
      """

    When I run `wp @testdir option get home`
    Then STDOUT should be:
      """
      http://example.com
      """

    When I try `wp @testdir option get home --path=testdir`
    Then STDERR should contain:
      """
      Parameter errors:
      """
    And STDERR should contain:
      """
      unknown --path parameter
      """

    When I run `wp @testdir eval 'echo get_current_user_id();' --user=admin`
    Then STDOUT should be:
      """
      1
      """

    Given a wp-cli.yml file:
      """
      @testdir:
        path: testdir
        user: admin
      """

    When I run `wp @testdir eval 'echo get_current_user_id();'`
    Then STDOUT should be:
      """
      1
      """

    When I try `wp @testdir eval 'echo get_current_user_id();' --user=admin`
    Then STDERR should contain:
      """
      Parameter errors:
      """
    And STDERR should contain:
      """
      unknown --user parameter
      """

  Scenario: Support global params specific to the WordPress install, not WP-CLI generally
    Given a WP install in 'testdir'
    And a wp-cli.yml file:
      """
      @testdir:
        path: testdir
        debug: true
      """

    When I run `wp @testdir option get home`
    Then STDOUT should be:
      """
      http://example.com
      """
    And STDERR should be empty

  Scenario: List available aliases
    Given an empty directory
    And a wp-cli.yml file:
      """
      @testdir:
        path: testdir
      """

    When I run `wp cli alias`
    Then STDOUT should be YAML containing:
      """
      @all: Run command against every registered alias.
      @testdir:
        path: testdir
      """

    When I run `wp cli aliases`
    Then STDOUT should be YAML containing:
      """
      @all: Run command against every registered alias.
      @testdir:
        path: testdir
      """

    When I run `wp cli alias --format=json`
    Then STDOUT should be JSON containing:
      """
      {"@all":"Run command against every registered alias.","@testdir":{"path":"testdir"}}
      """

  Scenario: Defining a project alias completely overrides a global alias
    Given a WP install in 'testdir'
    And a config.yml file:
      """
      @testdir:
        path: testdir
      """

    When I run `WP_CLI_CONFIG_PATH=config.yml wp @testdir option get home`
    Then STDOUT should be:
      """
      http://example.com
      """

    Given a wp-cli.yml file:
      """
      @testdir:
        path: none-existent-install
      """
    When I try `WP_CLI_CONFIG_PATH=config.yml wp @testdir option get home`
    Then STDERR should contain:
      """
      Error: This does not seem to be a WordPress install.
      """

  Scenario: Use a group of aliases to run a command against multiple installs
    Given a WP install in 'subdir1'
    And a WP install in 'subdir2'
    And a wp-cli.yml file:
      """
      @both:
        - @subdir1
        - @subdir2
      @invalid:
        - @subdir1
        - @subdir3
      @subdir1:
        path: subdir1
      @subdir2:
        path: subdir2
      """

    When I run `wp @subdir1 option update home 'http://apple.com'`
    And I run `wp @subdir1 option get home`
    Then STDOUT should contain:
      """
      http://apple.com
      """

    When I run `wp @subdir2 option update home 'http://google.com'`
    And I run `wp @subdir2 option get home`
    Then STDOUT should contain:
      """
      http://google.com
      """

    When I try `wp @invalid option get home`
    Then STDERR should be:
      """
      Error: Group '@invalid' contains one or more invalid aliases: @subdir3
      """

    When I run `wp @both option get home`
    Then STDOUT should be:
      """
      @subdir1
      http://apple.com
      @subdir2
      http://google.com
      """

    When I run `wp @both option get home --quiet`
    Then STDOUT should be:
      """
      http://apple.com
      http://google.com
      """

  Scenario: Register '@all' alias for running on one or more aliases
    Given a WP install in 'subdir1'
    And a WP install in 'subdir2'
    And a wp-cli.yml file:
      """
      @subdir1:
        path: subdir1
      @subdir2:
        path: subdir2
      """

    When I run `wp @subdir1 option update home 'http://apple.com'`
    And I run `wp @subdir1 option get home`
    Then STDOUT should contain:
      """
      http://apple.com
      """

    When I run `wp @subdir2 option update home 'http://google.com'`
    And I run `wp @subdir2 option get home`
    Then STDOUT should contain:
      """
      http://google.com
      """

    When I run `wp @all option get home`
    Then STDOUT should be:
      """
      @subdir1
      http://apple.com
      @subdir2
      http://google.com
      """

    When I run `wp @all option get home --quiet`
    Then STDOUT should be:
      """
      http://apple.com
      http://google.com
      """

  Scenario: Don't register '@all' when its already set
    Given a WP install in 'subdir1'
    And a WP install in 'subdir2'
    And a wp-cli.yml file:
      """
      @all:
        path: subdir1
      @subdir2:
        path: subdir2
      """

    When I run `wp @all option get home | wc -l`
    Then STDOUT should be:
      """
      1
      """

  Scenario: Error when '@all' is used without aliases defined
    Given an empty directory

    When I try `wp @all option get home`
    Then STDERR should be:
      """
      Error: Cannot use '@all' when no aliases are registered.
      """

  Scenario: Alias for a subsite of a multisite install
    Given a WP multisite subdomain install
    And a wp-cli.yml file:
      """
      url: example.com
      @subsite:
        url: subsite.example.com
      """

    When I run `wp site create --slug=subsite`
    Then STDOUT should not be empty

    When I run `wp option get siteurl`
    Then STDOUT should be:
      """
      http://example.com
      """

    When I run `wp @subsite option get siteurl`
    Then STDOUT should be:
      """
      http://subsite.example.com
      """

    When I try `wp @subsite option get siteurl --url=subsite.example.com`
    Then STDERR should be:
      """
      Error: Parameter errors:
       unknown --url parameter
      """
