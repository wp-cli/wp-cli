<?php

/**
 * Manage options.
 *
 * ## OPTIONS
 *
 * [--format=json]
 * : Encode/decode values as JSON.
 *
 * ## EXAMPLES
 *
 *     wp option get siteurl
 *
 *     wp option add my_option foobar
 *
 *     wp option update my_option '{"foo": "bar"}' --format=json
 *
 *     wp option delete my_option
 */
class Option_Command extends WP_CLI_Command {

	/**
	 * Get an option.
	 *
	 * @synopsis <key> [--format=<format>]
	 */
	public function get( $args, $assoc_args ) {
		list( $key ) = $args;

		$value = get_option( $key );

		if ( false === $value )
			die(1);

		WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Add an option.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : The name of the option to add.
	 *
	 * [<value>]
	 * : The value of the option to add. If ommited, the value is read from STDIN.
	 *
	 * [--format=<format>]
	 * : The serialization format for the value. Default is plaintext.
	 *
	 * [--autoload=<autoload>]
	 * : Should this option be automatically loaded. Accepted values: yes, no. Default: yes
	 *
	 * ## EXAMPLES
	 *
	 *     # Create an option by reading a JSON file
	 *     wp option add my_option --format=json < config.json
	 */
	public function add( $args, $assoc_args ) {
		$key = $args[0];

		$value = WP_CLI::get_value_from_arg_or_stdin( $args, 1 );
		$value = WP_CLI::read_value( $value, $assoc_args );

		if ( isset( $assoc_args['autoload'] ) && $assoc_args['autoload'] == 'no' ) {
			$autoload = 'no';
		} else {
			$autoload = 'yes';
		}

		if ( !add_option( $key, $value, '', $autoload ) ) {
			WP_CLI::error( "Could not add option '$key'. Does it already exist?" );
		} else {
			WP_CLI::success( "Added '$key' option." );
		}
	}

	/**
	 * List options.
	 *
	 * [--search=<sql-like-pattern>]
	 * : SQL pattern matching enables you to use "_" to match any single character and "%" to match an arbitrary number of characters.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--autoload]
	 * : Match only autoload options.
	 *
	 * [--total]
	 * : Display only the total size of matching options.
	 *
	 * [--format=<format>]
	 * : The serialization format for the value. Default is table.
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * This field will be displayed by default for each matching option:
	 *
	 * * option_name
	 *
	 * These fields are optionally available:
	 *
	 * * option_name
	 * * autoload
	 * * size
	 *
	 * @subcommand list
	 * @synopsis [--search=<sql-like-pattern>] [--total] [--autoload] [--fields=<fields>] [--format=<format>]
	 */
	public function list_( $args, $assoc_args ) {

		global $wpdb;
		$size_query = "LENGTH(option_value) AS size";
		$autoload_query = '';

		if ( isset( $assoc_args['search'] ) ) {
			$pattern = $assoc_args['search'];
		} else {
			$pattern = '%';
		}

		if ( isset( $assoc_args['fields'] ) ) {
			$fields = explode( ',', $assoc_args['fields'] );
		} else {
			$fields = array( 'option_name' );
		}

		if ( isset( $assoc_args['total'] ) ) {
			$fields = array( 'size' );
			$size_query = "SUM(LENGTH(option_value)) AS size";
		}

		if ( isset( $assoc_args['autoload'] ) ) {
			$autoload_query = " AND autoload='yes'";
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name,option_value,autoload," . $size_query
					. " FROM $wpdb->options WHERE option_name LIKE %s" . $autoload_query,
				$pattern
			)
		);

		$formatter = new \WP_CLI\Formatter(
			$assoc_args,
			$fields
		);
		$formatter->display_items( $results );
	}

	/**
	 * Update an option.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : The name of the option to add.
	 *
	 * [<value>]
	 * : The new value. If ommited, the value is read from STDIN.
	 *
	 * [--format=<format>]
	 * : The serialization format for the value. Default is plaintext.
	 *
	 * ## EXAMPLES
	 *
	 *     # Update an option by reading from a file
	 *     wp option update my_option < value.txt
	 *
	 *     # Update one option on multiple sites using xargs
	 *     wp site list --field=url | xargs -n1 -I {} sh -c 'wp --url={} option update <key> <value>'
	 *
	 * @alias set
	 */
	public function update( $args, $assoc_args ) {
		$key = $args[0];

		$value = WP_CLI::get_value_from_arg_or_stdin( $args, 1 );
		$value = WP_CLI::read_value( $value, $assoc_args );

		$result = update_option( $key, $value );

		// update_option() returns false if the value is the same
		if ( !$result && $value != get_option( $key ) ) {
			WP_CLI::error( "Could not update option '$key'." );
		} else {
			WP_CLI::success( "Updated '$key' option." );
		}
	}

	/**
	 * Delete an option.
	 *
	 * @synopsis <key>
	 */
	public function delete( $args ) {
		list( $key ) = $args;

		if ( !delete_option( $key ) ) {
			WP_CLI::error( "Could not delete '$key' option. Does it exist?" );
		} else {
			WP_CLI::success( "Deleted '$key' option." );
		}
	}
}

WP_CLI::add_command( 'option', 'Option_Command' );

