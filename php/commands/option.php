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

