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
    And a sample.php file:
      """
      <?php
      """
    And a wp-cli.yml file:
      """
      require: sample.php
      """

    When I run `wp --info`
    Then STDOUT should contain:
      """
      wp-cli.yml
      """

    When I run `wp core is-installed`
    Then STDOUT should be empty

    When I run `wp` from 'wp-content'
    Then STDOUT should not be empty

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

    When I run `mkdir -p other/subdir`
    And I run `wp core is-installed` from 'other/subdir'
    Then STDOUT should be empty

  Scenario: WP in a subdirectory (autodetected)
    Given a WP install in 'core'

    Given an index.php file:
    """
    require('./core/wp-blog-header.php');
    """
    When I run `wp core is-installed`
    Then STDOUT should be empty

    Given an index.php file:
    """
    require dirname(__FILE__) . '/core/wp-blog-header.php';
    """
    When I run `wp core is-installed`
    Then STDOUT should be empty

    When I run `mkdir -p other/subdir`
    And I run `echo '<?php // Silence is golden' > other/subdir/index.php`
    And I run `wp core is-installed` from 'other/subdir'
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

  Scenario: Disabled commands
    Given a WP install
    And a config.yml file:
      """
      disabled_commands:
        - eval-file
        - core multisite-convert
      """

    When I run `WP_CLI_CONFIG_PATH=config.yml wp`
    Then STDOUT should not contain:
      """
      eval-file
      """

    When I try `WP_CLI_CONFIG_PATH=config.yml wp help eval-file`
    Then STDERR should be:
      """
      Error: The 'eval-file' command has been disabled from the config file.
      """

    When I run `WP_CLI_CONFIG_PATH=config.yml wp core`
    Then STDOUT should not contain:
      """
      or: wp core multisite-convert
      """

    When I run `WP_CLI_CONFIG_PATH=config.yml wp help core`
    Then STDOUT should not contain:
      """
      multisite-convert
      """

    When I try `WP_CLI_CONFIG_PATH=config.yml wp core multisite-convert`
    Then STDERR should contain:
      """
      command has been disabled
      """

    When I try `WP_CLI_CONFIG_PATH=config.yml wp help core multisite-convert`
    Then STDERR should contain:
      """
      Error: The 'core multisite-convert' command has been disabled from the config file.
      """

  Scenario: 'core config' parameters
    Given an empty directory
    And WP files
    And a wp-cli.yml file:
      """
      core config:
        dbname: wordpress
        dbuser: root
        extra-php: |
          define( 'WP_DEBUG', true );
          define( 'WP_POST_REVISIONS', 50 );
      """

    When I run `wp core config --skip-check`
    And I run `grep WP_POST_REVISIONS wp-config.php`
    Then STDOUT should not be empty

  Scenario: Command-specific configs
    Given a WP install
    And a wp-cli.yml file:
      """
      eval:
        foo: bar
      post list:
        format: count
      """

    # Arbitrary values should be passed, without warnings
    When I run `wp eval 'echo json_encode( $assoc_args );'`
    Then STDOUT should be JSON containing:
      """
      {"foo": "bar"}
      """

    # CLI args should trump config values
    When I run `wp post list`
    Then STDOUT should be a number
    When I run `wp post list --format=json`
    Then STDOUT should not be a number

  Scenario: Required files should not be loaded twice
    Given an empty directory
    And a custom-file.php file:
      """
      <?php
      define( 'FOOBUG', 'BAR' );
      """
    And a test-dir/config.yml file:
      """
      require:
        - ../custom-file.php
      """
    And a wp-cli.yml file:
      """
      require:
        - custom-file.php
      """

    When I run `WP_CLI_CONFIG_PATH=test-dir/config.yml wp help`
    Then STDERR should be empty
