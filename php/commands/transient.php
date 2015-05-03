<?php

/**
 * Manage transients.
 *
 * ## EXAMPLES
 *
 *     wp transient set my_key my_value 300
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

		$expiration = \WP_CLI\Utils\get_flag_value( $args, 2, 0 );

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
	 * See whether the transients API is using an object cache or the options table.
	 */
	public function type() {
		global $_wp_using_ext_object_cache, $wpdb;

		if ( $_wp_using_ext_object_cache )
			$message = 'Transients are saved to the object cache.';
		else
			$message = 'Transients are saved to the ' . $wpdb->prefix . 'options table.';

		WP_CLI::line( $message );
	}

	/**
	 * Delete all expired transients.
	 *
	 * @subcommand delete-expired
	 */
	public function delete_expired() {
		global $wpdb, $_wp_using_ext_object_cache;

		// Always delete all transients from DB too.
		$time = current_time('timestamp');
		$count = $wpdb->query(
			"DELETE a, b FROM $wpdb->options a, $wpdb->options b WHERE
			a.option_name LIKE '\_transient\_%' AND
			a.option_name NOT LIKE '\_transient\_timeout\_%' AND
			b.option_name = CONCAT( '_transient_timeout_', SUBSTRING( a.option_name, 12 ) )
			AND b.option_value < $time"
		);

		if ( $count > 0 ) {
			WP_CLI::success( "$count expired transients deleted from the database." );
		} else {
			WP_CLI::success( "No expired transients found" );
		}

		if ( $_wp_using_ext_object_cache ) {
			WP_CLI::warning( 'Transients are stored in an external object cache, and this command only deletes those stored in the database. You must flush the cache to delete all transients.');
		}
	}

	/**
	 * Delete all transients.
	 *
	 * @subcommand delete-all
	 */
	public function delete_all() {
		global $wpdb, $_wp_using_ext_object_cache;

		// Always delete all transients from DB too.
		$count = $wpdb->query(
			"DELETE FROM $wpdb->options
			WHERE option_name LIKE '\_transient\_%'
			OR option_name LIKE '\_site\_transient\_%'"
		);

		if ( $count > 0 ) {
			WP_CLI::success( "$count transients deleted from the database." );
		} else {
			WP_CLI::success( "No transients found" );
		}

		if ( $_wp_using_ext_object_cache ) {
			WP_CLI::warning( 'Transients are stored in an external object cache, and this command only deletes those stored in the database. You must flush the cache to delete all transients.');
		}
	}

}

WP_CLI::add_command( 'transient', 'Transient_Command' );

