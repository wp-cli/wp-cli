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

  Scenario: A catch-all command warns about a parameter that looks like a typo
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
    And STDERR should be empty

    # One edit away from a documented parameter, so it is reported rather than
    # silently ignored. This is what the command used to accept without a word.
    # The parameter still reaches the command and the exit code is unaffected:
    # the synopsis says arbitrary keys are legitimate, so the command decides.
    When I try `wp --require=custom-cmd.php entity update 1 --entity_nme=beta`
    Then the return code should be 0
    And STDERR should contain:
      """
      Warning: --entity_nme looks like a typo of --entity_name
      """
    And STDOUT should contain:
      """
      entity_nme=beta
      """
    And STDOUT should contain:
      """
      Success: Updated 1.
      """

    # Anything further away is what the catch-all exists for: it has to reach the
    # command without a word.
    When I run `wp --require=custom-cmd.php entity update 1 --custom_field=gamma`
    Then STDOUT should contain:
      """
      custom_field=gamma
      """
    And STDOUT should contain:
      """
      Success: Updated 1.
      """
    And STDERR should be empty

  Scenario: A catch-all command warns about a typo on a real command
    Given a WP install

    # The password is a documented field of `wp user update`, and the command has
    # a `--<field>=<value>` catch-all, so `--user-pass` used to be accepted and
    # then never applied. It is still passed through, but no longer in silence.
    When I try `wp user update 1 --user-pass=secret`
    Then the return code should be 0
    And STDERR should contain:
      """
      Warning: --user-pass looks like a typo of --user_pass
      """
    And STDOUT should contain:
      """
      Success: Updated user 1.
      """

    # A key that is nothing like a documented field is what the catch-all is
    # for, so nothing is said about it.
    When I run `wp user update 1 --some_plugin_key=value`
    Then STDOUT should contain:
      """
      Success: Updated user 1.
      """
    And STDERR should be empty

    When I run `wp user update 1 --user_pass=secret`
    Then STDOUT should contain:
      """
      Success: Updated user 1.
      """
    And STDERR should be empty

  Scenario: A catch-all read command still accepts a real argument that resembles a documented one
    Given a WP multisite install

    # `site_id` is a real column of the sites table and a real WP_Site_Query
    # argument, two edits from the documented `--site__in`. Whatever the check
    # makes of it (newer entity-command releases document it as an alias of
    # `--network`, older ones do not), the argument has to reach the command
    # and the command has to succeed: the synopsis says arbitrary keys are
    # legitimate, and this one is.
    When I try `wp site list --site_id=1 --format=ids`
    Then the return code should be 0
    And STDOUT should be:
      """
      1
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
    And STDERR should be empty

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

  Scenario: A catch-all command does not measure its parameters against positional ones
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      /**
       * Runs a query.
       *
       * <sql>
       * : The query to run.
       *
       * [--format=<format>]
       * : A documented parameter.
       *
       * [--<field>=<value>]
       * : Options passed through to the client.
       *
       * @when before_wp_load
       */
      WP_CLI::add_command(
      	'entity query',
      	function ( $args, $assoc_args ) {
      		ksort( $assoc_args );
      		foreach ( $assoc_args as $key => $value ) {
      			WP_CLI::log( "{$key}={$value}" );
      		}
      		WP_CLI::success( "Ran {$args[0]}." );
      	}
      );
      """

    # '--xml' is two edits from the positional '<sql>', and a real option of the
    # client the command hands its parameters to. A positional argument cannot
    # be spelled `--sql` in the first place, so it is not a candidate.
    When I run `wp --require=custom-cmd.php entity query 'SELECT 1' --xml=1`
    Then STDOUT should contain:
      """
      xml=1
      """
    And STDOUT should contain:
      """
      Success: Ran SELECT 1.
      """
    And STDERR should be empty

    # The documented parameters of the command remain candidates.
    When I try `wp --require=custom-cmd.php entity query 'SELECT 1' --fomat=json`
    Then the return code should be 0
    And STDERR should contain:
      """
      Warning: --fomat looks like a typo of --format
      """

  Scenario: A catch-all command does not treat a prefix of a documented parameter as a typo
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      /**
       * Lists entities.
       *
       * [--paged=<page>]
       * : A documented parameter.
       *
       * [--tag_id=<id>]
       * : A documented parameter.
       *
       * [--<field>=<value>]
       * : One or more query arguments.
       *
       * @when before_wp_load
       */
      WP_CLI::add_command(
      	'entity list',
      	function ( $args, $assoc_args ) {
      		ksort( $assoc_args );
      		foreach ( $assoc_args as $key => $value ) {
      			WP_CLI::log( "{$key}={$value}" );
      		}
      		WP_CLI::success( 'Listed.' );
      	}
      );
      """

    # Documented families nest: '--page' is one edit from '--paged' and '--tag'
    # is a prefix of '--tag_id', and both are real query arguments. A typo is
    # not usually a clean truncation, so a prefix of a documented parameter,
    # or a parameter a documented one is a prefix of, is passed through quietly.
    When I run `wp --require=custom-cmd.php entity list --page=2 --tag_id_x=3`
    Then STDOUT should contain:
      """
      page=2
      """
    And STDOUT should contain:
      """
      tag_id_x=3
      """
    And STDERR should be empty

    # A transposition is not a prefix, so it is still reported.
    When I try `wp --require=custom-cmd.php entity list --pgaed=2`
    Then the return code should be 0
    And STDERR should contain:
      """
      Warning: --pgaed looks like a typo of --paged
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
    # reason to doubt a field, so it is not consulted there.
    When I run `wp --require=custom-cmd.php entity update 1 --add=beta`
    Then STDOUT should contain:
      """
      add=beta
      """
    And STDOUT should contain:
      """
      Success: Updated 1.
      """
    And STDERR should be empty

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
