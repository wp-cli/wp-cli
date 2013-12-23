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
	 * @synopsis <key> <value> [--format=<format>]
	 */
	public function add( $args, $assoc_args ) {
		$key = $args[0];

		$value = WP_CLI::read_value( $args[1], $assoc_args );

		if ( !add_option( $key, $value ) ) {
			WP_CLI::error( "Could not add option '$key'. Does it already exist?" );
		} else {
			WP_CLI::success( "Added '$key' option." );
		}
	}

	/**
	 * Update an option.
	 *
	 * @alias set
	 * @synopsis <key> <value> [--format=<format>]
	 */
	public function update( $args, $assoc_args ) {
		$key = $args[0];

		$value = WP_CLI::read_value( $args[1], $assoc_args );

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

