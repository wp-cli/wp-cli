Feature: Create shortcuts to specific WordPress installs

  Scenario: Alias for a path to a specific WP installation
    Given a WP installation in 'foo'
    And I run `mkdir bar`
    And a wp-cli.yml file:
      """
      @foo:
        path: foo
      """

    When I try `wp core is-installed`
    Then STDERR should contain:
      """
      Error: This does not seem to be a WordPress installation.
      """
    And the return code should be 1

    When I run `wp @foo core is-installed`
    Then the return code should be 0

    When I run `cd bar; wp @foo core is-installed`
    Then the return code should be 0

  Scenario: Error when invalid alias provided
    Given an empty directory

    When I try `wp @test option get home`
    Then STDERR should be:
      """
      Error: Alias '@test' not found.
      """

  Scenario: Provide suggestion when invalid alias is provided
    Given an empty directory
    And a wp-cli.yml file:
      """
      @test2:
        path: foo
      """

    When I try `wp @test option get home`
    Then STDERR should be:
      """
      Error: Alias '@test' not found.
      Did you mean '@test2'?
      """

  Scenario: Treat global params as local when included in alias
    Given a WP installation in 'foo'
    And a wp-cli.yml file:
      """
      @foo:
        path: foo
      """

    When I run `wp @foo option get home`
    Then STDOUT should be:
      """
      https://example.com
      """

    When I try `wp @foo option get home --path=foo`
    Then STDERR should contain:
      """
      Parameter errors:
      """
    And STDERR should contain:
      """
      unknown --path parameter
      """

    When I run `wp @foo eval 'echo get_current_user_id();' --user=admin`
    Then STDOUT should be:
      """
      1
      """

    Given a wp-cli.yml file:
      """
      @foo:
        path: foo
        user: admin
      """

    When I run `wp @foo eval 'echo get_current_user_id();'`
    Then STDOUT should be:
      """
      1
      """

    When I try `wp @foo eval 'echo get_current_user_id();' --user=admin`
    Then STDERR should contain:
      """
      Parameter errors:
      """
    And STDERR should contain:
      """
      unknown --user parameter
      """

  Scenario: Support global params specific to the WordPress install, not WP-CLI generally
    Given a WP installation in 'foo'
    And a wp-cli.yml file:
      """
      @foo:
        path: foo
        debug: true
      """

    When I run `wp @foo option get home`
    Then STDOUT should be:
      """
      https://example.com
      """
    And STDERR should be empty

  Scenario: List available aliases
    Given an empty directory
    And a wp-cli.yml file:
      """
      @foo:
        path: foo
      """

    When I run `wp eval --skip-wordpress 'echo realpath( getenv( "RUN_DIR" ) );'`
    Then save STDOUT as {TEST_DIR}

    When I run `wp cli alias list`
    Then STDOUT should be YAML containing:
      """
      @all: Run command against every registered alias.
      @foo:
        path: {TEST_DIR}/foo
      """

    When I run `wp cli aliases`
    Then STDOUT should be YAML containing:
      """
      @all: Run command against every registered alias.
      @foo:
        path: {TEST_DIR}/foo
      """

    When I run `wp cli alias list --format=json`
    Then STDOUT should be JSON containing:
      """
      {"@all":"Run command against every registered alias.","@foo":{"path":"{TEST_DIR}/foo"}}
      """

    When I run `wp cli aliases --format=json`
    Then STDOUT should be JSON containing:
      """
      {"@all":"Run command against every registered alias.","@foo":{"path":"{TEST_DIR}/foo"}}
      """

  Scenario: Get alias information
    Given a WP installation in 'foo'
    And a wp-cli.yml file:
      """
      @foo:
        ssh: user@host:/path/to/wordpress
      """

    When I run `wp cli alias get @foo`
    Then STDOUT should be:
      """
      ssh: user@host:/path/to/wordpress
      """

    When I try `wp cli alias get @someotherfoo`
    Then STDERR should be:
      """
      Error: No alias found with key '@someotherfoo'.
      """

  Scenario: Adds proxyjump to ssh command
    Given a WP installation in 'foo'
    And a wp-cli.yml file:
      """
      @foo:
        ssh: user@host:/path/to/wordpress
        proxyjump: proxyhost
      """

    When I try `wp @foo --debug --version`
    Then STDERR should contain:
      """
      Running SSH command: ssh -q -J 'proxyhost' 
      """

  Scenario: Adds key to ssh command
    Given a WP installation in 'foo'
    And a wp-cli.yml file:
      """
      @foo:
        ssh: user@host:/path/to/wordpress
        key: identityfile.key
      """

    When I try `wp @foo --debug --version`
    Then STDERR should contain:
      """
      Running SSH command: ssh -q -i 'identityfile.key' 
      """

  Scenario: Add an alias
    Given a WP installation in 'foo'
    And a wp-cli.yml file:
    """
    @foo:
      ssh: wpcli@wp-cli.org:2222
    """

    When I run `wp cli alias add @dev --set-user=wpcli --set-path=/path/to/wordpress --config=project`
    Then STDOUT should be:
       """
       Success: Added '@dev' alias.
       """
    When I run `wp cli alias list`
    Then STDOUT should be YAML containing:
      """
      @all: Run command against every registered alias.
      @foo:
        ssh: wpcli@wp-cli.org:2222
      @dev:
        user: wpcli
        path: /path/to/wordpress
      """

    When I try `wp cli alias add @something --config=project`
    Then STDERR should be:
    """
    Error: No valid arguments passed.
    """

    When I try `wp cli alias add @something --set-user= --config=project`
    Then STDERR should be:
    """
    Error: No value passed to arguments.
    """

    When I try `wp cli alias add @something --set-path=/new/path --grouping=foo,dev --config=project`
    Then STDERR should be:
    """
    Error: --grouping argument works alone. Found invalid arg(s) 'set-path'.
    """

  Scenario: Delete an alias
    Given a WP installation in 'foo'
    And a wp-cli.yml file:
    """
    @foo:
      ssh: foo@bar:/path/to/wordpress
    @dev:
      ssh: user@hostname:/path/to/wordpress
    """

    When I run `wp cli alias delete @dev --config=project`
    Then STDOUT should be:
      """
      Success: Deleted '@dev' alias.
      """
    When I run `wp cli alias list`
    Then STDOUT should be YAML containing:
      """
      @all: Run command against every registered alias.
      @foo:
        ssh: foo@bar:/path/to/wordpress
      """
    When I try `wp cli alias delete @dev`
    Then STDERR should be:
      """
      Error: No alias found with key '@dev'.
      """

    When I try `wp cli alias update @foo`
    Then STDERR should be:
    """
    Error: No valid arguments passed.
    """

  Scenario: Update an alias
    Given a WP installation in 'foo'
    And a wp-cli.yml file:
    """
    @foo:
      user: wpcli
    @foopath:
      path: /home/wpcli/sites/wpcli
    @foogroup:
      - @foo
      - @foopath
    """

    When I run `wp cli alias update @foo --set-user=newuser --config=project`
    Then STDOUT should be:
       """
      Success: Updated '@foo' alias.
       """
    When I run `wp cli alias list`
    Then STDOUT should be YAML containing:
      """
      @all: Run command against every registered alias.
      @foo:
        user: newuser
      @foopath:
        path: /home/wpcli/sites/wpcli
      @foogroup:
        - @foo
        - @foopath
      """
    When I try `wp cli alias update @otherfoo --set-ssh=foo@host --set-path=/some/path`
    Then STDERR should be:
      """
      Error: No alias found with key '@otherfoo'.
      """

    When I try `wp cli alias update @foogroup --set-ssh=foo@host`
    Then STDERR should be:
      """
      Error: Trying to update group alias with invalid arguments.
      """

    When I try `wp cli alias update @foo --grouping=foo@host --set-user=wpcli`
    Then STDERR should be:
      """
      Error: --grouping argument works alone. Found invalid arg(s) 'set-user'.
      """

    When I try `wp cli alias update @foo --grouping=foo@host`
    Then STDERR should be:
      """
      Error: Trying to update simple alias with invalid --grouping argument.
      """

    When I try `wp cli alias update @foo --set-path=/new/path`
    Then STDOUT should be:
    """
    Success: Updated '@foo' alias.
    """

    When I run `wp cli alias list`
    Then STDOUT should be YAML containing:
      """
      @all: Run command against every registered alias.
      @foo:
        user: newuser
        path: /new/path
      @foopath:
        path: /home/wpcli/sites/wpcli
      @foogroup:
        - @foo
        - @foopath
      """

  Scenario: Defining a project alias completely overrides a global alias
    Given a WP installation in 'foo'
    And a config.yml file:
      """
      @foo:
        path: foo
      """

    When I run `WP_CLI_CONFIG_PATH=config.yml wp @foo option get home`
    Then STDOUT should be:
      """
      https://example.com
      """

    Given a wp-cli.yml file:
      """
      @foo:
        path: none-existent-install
      """
    When I try `WP_CLI_CONFIG_PATH=config.yml wp @foo option get home`
    Then STDERR should contain:
      """
      Error: This does not seem to be a WordPress installation.
      """

  Scenario: Use a group of aliases to run a command against multiple installs
    Given a WP installation in 'foo'
    And a WP installation in 'bar'
    And a wp-cli.yml file:
      """
      @both:
        - @foo
        - @bar
      @invalid:
        - @foo
        - @baz
      @foo:
        path: foo
      @bar:
        path: bar
      """

    When I run `wp @foo option update home 'http://apple.com'`
    And I run `wp @foo option get home`
    Then STDOUT should contain:
      """
      http://apple.com
      """

    When I run `wp @bar option update home 'http://google.com'`
    And I run `wp @bar option get home`
    Then STDOUT should contain:
      """
      http://google.com
      """

    When I try `wp @invalid option get home`
    Then STDERR should be:
      """
      Error: Group '@invalid' contains one or more invalid aliases: @baz
      """

    When I run `wp @both option get home`
    Then STDOUT should be:
      """
      @foo
      http://apple.com
      @bar
      http://google.com
      """

    When I run `wp @both option get home --quiet`
    Then STDOUT should be:
      """
      http://apple.com
      http://google.com
      """

  Scenario: Register '@all' alias for running on one or more aliases
    Given a WP installation in 'foo'
    And a WP installation in 'bar'
    And a wp-cli.yml file:
      """
      @foo:
        path: foo
      @bar:
        path: bar
      """

    When I run `wp @foo option update home 'http://apple.com'`
    And I run `wp @foo option get home`
    Then STDOUT should contain:
      """
      http://apple.com
      """

    When I run `wp @bar option update home 'http://google.com'`
    And I run `wp @bar option get home`
    Then STDOUT should contain:
      """
      http://google.com
      """

    When I run `wp @all option get home`
    Then STDOUT should be:
      """
      @foo
      http://apple.com
      @bar
      http://google.com
      """

    When I run `wp @all option get home --quiet`
    Then STDOUT should be:
      """
      http://apple.com
      http://google.com
      """

  Scenario: Don't register '@all' when its already set
    Given a WP installation in 'foo'
    And a WP installation in 'bar'
    And a wp-cli.yml file:
      """
      @all:
        path: foo
      @bar:
        path: bar
      """

    When I run `wp @all option get home | wc -l | tr -d ' '`
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
    Given a WP multisite subdomain installation
    And a wp-cli.yml file:
      """
      url: https://example.com
      @subsite:
        url: https://subsite.example.com
      """

    When I run `wp site create --slug=subsite`
    Then STDOUT should not be empty

    When I run `wp option get siteurl`
    Then STDOUT should be:
      """
      https://example.com
      """

    # TODO: The HTTPS default is currently not forwarded to subsite creation.
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

  Scenario: Global parameters should be passed to grouped aliases
    Given a WP installation in 'foo'
    And a WP installation in 'bar'
    And a wp-cli.yml file:
      """
      @foo:
        path: foo
      @bar:
        path: bar
      @foobar:
        - @foo
        - @bar
      """

    When I try `wp core is-installed --allow-root --debug`
    Then STDERR should contain:
      """
      Error: This does not seem to be a WordPress installation.
      """
    And STDERR should contain:
      """
      core is-installed --allow-root --debug
      """
    And the return code should be 1

    When I try `wp @foo core is-installed --allow-root --debug`
    Then the return code should be 0
    And STDERR should contain:
      """
      @foo core is-installed --allow-root --debug
      """

    When I try `cd bar; wp @bar core is-installed --allow-root --debug`
    Then the return code should be 0
    And STDERR should contain:
      """
      @bar core is-installed --allow-root --debug
      """

    When I try `wp @foobar core is-installed --allow-root --debug`
    Then the return code should be 0
    And STDERR should contain:
      """
      @foobar core is-installed --allow-root --debug
      """
    And STDERR should contain:
      """
      @foo core is-installed --allow-root --debug
      """
    And STDERR should contain:
      """
      @bar core is-installed --allow-root --debug
      """

  Scenario Outline: Check that proc_open() and proc_close() aren't disabled for grouped aliases
    Given a WP installation in 'foo'
    And a WP installation in 'bar'
    And a wp-cli.yml file:
      """
      @foo:
        path: foo
      @bar:
        path: bar
      @foobar:
        - @foo
        - @bar
      """

    When I try `{INVOKE_WP_CLI_WITH_PHP_ARGS--ddisable_functions=<func>} @foobar core is-installed`
    Then STDERR should contain:
      """
      Error: Cannot do 'group alias': The PHP functions `proc_open()` and/or `proc_close()` are disabled
      """
    And the return code should be 1

    Examples:
      | func       |
      | proc_open  |
      | proc_close |

  Scenario: An alias is a group of aliases
    Given a WP install
    And a wp-cli.yml file:
      """
      @foo:
        path: foo
      @bar:
        path: bar
      @both:
       - @foo
       - @bar
      """

    When I try `wp cli alias is-group @both`
    Then the return code should be 0

  Scenario: An alias is not a group of aliases
    Given a WP install
    And a wp-cli.yml file:
      """
      @foo:
        path: foo
      """

    When I try `wp cli alias is-group @foo`
    Then the return code should be 1
