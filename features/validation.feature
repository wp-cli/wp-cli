Feature: Argument validation
  In order to catch errors fast
  As a user
  I need to see warnings and errors when I pass incorrect arguments

  Scenario: Passing zero arguments to a variadic command
    Given a WP installation

    When I try `wp plugin install`
    Then the return code should be 1
    And STDOUT should contain:
      """
      usage: wp plugin install
      """

  Scenario: Validation for early commands
    Given an empty directory
    And WP files

    When I try `wp config create --dbprefix=invalid- --dbname=foo --dbpass=bar --dbuser=baz --skip-check`
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: --dbprefix can only contain numbers, letters, and underscores.
      """

    When I try `wp config create --invalid --other-invalid`
    Then the return code should be 1
    And STDERR should contain:
      """
      unknown --invalid parameter
      """
    And STDERR should contain:
      """
      unknown --other-invalid parameter
      """

    When I try `wp core version invalid`
    Then the return code should be 1
    And STDERR should contain:
      """
      Error: Too many positional arguments: invalid
      """
    And STDOUT should be empty

  Scenario: A catch-all command reports a parameter that looks like a typo
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      /**
       * Updates an entity.
       *
       * <entity_id>
       * : The entity to update.
       *
       * [--entity_name=<entity_name>]
       * : A documented parameter.
       *
       * [--create=<create>]
       * : A documented parameter the built-in alias map points 'add' at.
       *
       * [--<field>=<value>]
       * : One or more fields to update.
       *
       * @when before_wp_load
       */
      WP_CLI::add_command(
      	'entity update',
      	function ( $args, $assoc_args ) {
      		ksort( $assoc_args );
      		foreach ( $assoc_args as $key => $value ) {
      			WP_CLI::log( "{$key}={$value}" );
      		}
      		WP_CLI::success( "Updated {$args[0]}." );
      	}
      );
      """

    # A documented parameter is untouched by any of this.
    When I run `wp --require=custom-cmd.php entity update 1 --entity_name=beta`
    Then STDOUT should contain:
      """
      entity_name=beta
      """
    And STDOUT should contain:
      """
      Success: Updated 1.
      """

    # One edit away from a documented parameter, so it is reported rather than
    # silently ignored. This is what the command used to accept without a word.
    When I try `wp --require=custom-cmd.php entity update 1 --entity_nme=beta`
    Then the return code should be 1
    And STDERR should contain:
      """
      unknown --entity_nme parameter
      """
    And STDERR should contain:
      """
      Did you mean '--entity_name'?
      """
    And STDOUT should be empty

    # Anything further away is what the catch-all exists for: it has to reach the
    # command, not merely avoid an error.
    When I run `wp --require=custom-cmd.php entity update 1 --custom_field=gamma`
    Then STDOUT should contain:
      """
      custom_field=gamma
      """
    And STDOUT should contain:
      """
      Success: Updated 1.
      """

  Scenario: A catch-all command does not measure its parameters against the global ones
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      /**
       * Updates an entity.
       *
       * <entity_id>
       * : The entity to update.
       *
       * [--entity_name=<entity_name>]
       * : A documented parameter.
       *
       * [--create=<create>]
       * : A documented parameter the built-in alias map points 'add' at.
       *
       * [--<field>=<value>]
       * : One or more fields to update.
       *
       * @when before_wp_load
       */
      WP_CLI::add_command(
      	'entity update',
      	function ( $args, $assoc_args ) {
      		ksort( $assoc_args );
      		foreach ( $assoc_args as $key => $value ) {
      			WP_CLI::log( "{$key}={$value}" );
      		}
      		WP_CLI::success( "Updated {$args[0]}." );
      	}
      );

      /**
       * Creates an entity.
       *
       * [--entity_name=<entity_name>]
       * : A documented parameter.
       *
       * [--create=<create>]
       * : A documented parameter the built-in alias map points 'add' at.
       *
       * @when before_wp_load
       */
      WP_CLI::add_command(
      	'entity create',
      	function ( $args, $assoc_args ) {
      		WP_CLI::success( 'Created.' );
      	}
      );
      """

    # '--cat' is two edits from the global '--path', and a real query var on
    # commands like this one. The global parameters share a namespace with the
    # fields a catch-all command takes, so they are not candidates for it.
    When I run `wp --require=custom-cmd.php entity update 1 --cat=5`
    Then STDOUT should contain:
      """
      cat=5
      """
    And STDOUT should contain:
      """
      Success: Updated 1.
      """

    # The same parameter on a command with no catch-all is still reported, so
    # the global parameters keep helping everywhere they did before.
    When I try `wp --require=custom-cmd.php entity create --cat=5`
    Then the return code should be 1
    And STDERR should contain:
      """
      unknown --cat parameter
      """
    And STDERR should contain:
      """
      Did you mean '--path'?
      """

  Scenario: A catch-all command does not consult the command alias map
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      /**
       * Updates an entity.
       *
       * <entity_id>
       * : The entity to update.
       *
       * [--create=<create>]
       * : A documented parameter the built-in alias map points 'add' at.
       *
       * [--<field>=<value>]
       * : One or more fields to update.
       *
       * @when before_wp_load
       */
      WP_CLI::add_command(
      	'entity update',
      	function ( $args, $assoc_args ) {
      		ksort( $assoc_args );
      		foreach ( $assoc_args as $key => $value ) {
      			WP_CLI::log( "{$key}={$value}" );
      		}
      		WP_CLI::success( "Updated {$args[0]}." );
      	}
      );

      /**
       * Creates an entity.
       *
       * [--create=<create>]
       * : A documented parameter the built-in alias map points 'add' at.
       *
       * @when before_wp_load
       */
      WP_CLI::add_command(
      	'entity create',
      	function ( $args, $assoc_args ) {
      		WP_CLI::success( 'Created.' );
      	}
      );
      """

    # The alias map is command vocabulary and ignores the threshold: 'add' is
    # five edits from 'create'. On a catch-all command it would be the only
    # reason to reject a field, so it is not consulted there.
    When I run `wp --require=custom-cmd.php entity update 1 --add=beta`
    Then STDOUT should contain:
      """
      add=beta
      """
    And STDOUT should contain:
      """
      Success: Updated 1.
      """

    # Where the suggestion only decorates an error raised on other grounds, the
    # alias map still applies.
    When I try `wp --require=custom-cmd.php entity create --add=beta`
    Then the return code should be 1
    And STDERR should contain:
      """
      unknown --add parameter
      """
    And STDERR should contain:
      """
      Did you mean '--create'?
      """
