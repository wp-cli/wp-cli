<?php

WP_CLI::add_command('option', 'Option_Command');

/**
 * Implement option command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Option_Command extends WP_CLI_Command {

	/**
	 * Get an option.
	 *
	 * @synopsis <key> [--json]
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
	 * @synopsis <key> <value> [--json]
	 */
	public function add( $args, $assoc_args ) {
		$key = $args[0];

		$value = WP_CLI::read_value( $args[1], $assoc_args );

		if ( !add_option( $key, $value ) ) {
			WP_CLI::error( "Could not add option '$key'. Does it already exist?" );
		}
	}

	/**
	 * Update an option.
	 *
	 * @alias set
	 * @synopsis <key> <value> [--json]
	 */
	public function update( $args, $assoc_args ) {
		$key = $args[0];

		$value = WP_CLI::read_value( $args[1], $assoc_args );

		if ( $value === get_option( $key ) )
			return;

		if ( !update_option( $key, $value ) ) {
			WP_CLI::error( "Could not update option '$key'." );
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
		}
	}
}
