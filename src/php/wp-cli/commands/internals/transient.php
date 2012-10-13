<?php

WP_CLI::add_command( 'transient', 'Transient_Command' );

/**
 * Implement transient commands.
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Transient_Command extends WP_CLI_Command {

	/**
	 * Get a transient value.
	 *
	 * @synopsis <key> [--json]
	 */
	public function get( $args, $assoc_args ) {
		list( $key ) = $args;

		$value = get_transient( $key );

		if ( false === $value ) {
			WP_CLI::warning( 'Transient with key "' . $key . '" is not set.' );
			exit;
		}

		WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Set a transient value. <expiration> is the time until expiration, in seconds.
	 *
	 * @synopsis <key> <value> [<expiration>]
	 */
	public function set( $args ) {
		list( $key, $value ) = $args;

		$expiration = isset( $args[2] ) ? $args[2] : 0;

		if ( set_transient( $key, $value, $expiration ) )
			WP_CLI::success( 'Transient added.' );
		else
			WP_CLI::error( 'Transient could not be set.' );
	}

	/**
	 * Delete a transient value.
	 *
	 * @synopsis <key>
	 */
	public function delete( $args ) {
		list( $key ) = $args;

		if ( delete_transient( $key ) ) {
			WP_CLI::success( 'Transient deleted.' );
		} else {
			if ( get_transient( $key ) )
				WP_CLI::error( 'Transient was not deleted even though the transient appears to exist.' );
			else
				WP_CLI::warning( 'Transient was not deleted; however, the transient does not appear to exist.' );
		}
	}

	/**
	 * See wether the transients API is using an object cache or the options table.
	 */
	public function type() {
		global $_wp_using_ext_object_cache, $wpdb;

		if ( $_wp_using_ext_object_cache )
			$message = 'Transients are saved to the object cache.';
		else
			$message = 'Transients are saved to the ' . $wpdb->prefix . 'options table.';

		WP_CLI::line( $message );
	}
}
