<?php

WP_CLI::addCommand( 'transient', 'TransientCommand' );

/**
 * Implement transient commands.
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class TransientCommand extends WP_CLI_Command {

	/**
	 * Gets a value using the "get_transient" function.
	 *
	 * @param array $args
	 */
	public function get( $args ) {
		if ( empty( $args ) ) {
			WP_CLI::error( 'Usage: wp transient get <key>' );
			exit;
		}

		list( $key ) = $args;

		$value = get_transient( $key );

		if ( false === $value ) {
			WP_CLI::warning( 'Transient with key "' . $key . '" is not set.' );
			exit;
		}
			
		if ( is_array( $value ) || is_object( $value ) )
			WP_CLI::line( var_export( $value ) );
		else
			WP_CLI::success( $value );
	}

	/**
	 * Sets a value using the "set_transient" function.
	 *
	 * @param array $args
	 */
	public function set( $args ) {
		if ( count( $args ) < 2 ) {
			WP_CLI::error( 'usage: wp transient set <key> <value> [expiration]' );
			exit;
		}

		list( $key, $value, $expiration ) = $args;

		$expiration = isset( $expiration ) ? $expiration : 0;

		if ( set_transient( $key, $value, $expiration ) )
			WP_CLI::success( 'Transient added.' );
		else
			WP_CLI::error( 'Transient could not be set.' );
	}

	/**
	 * Deletes a value using the "delete_transient" function.
	 *
	 * @param array $args
	 */
	public function delete( $args ) {
		if ( empty( $args ) ) {
			WP_CLI::warning( 'Usage: wp transient delete <key>' );
			exit;
		}

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
	 * Indicates whether the transients API is using the object cache or options table in the current environment.
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
	 * Displays the help message.
	 */
	static function help() {
		WP_CLI::line( <<<EOB
usage: wp transient get <key>
   or: wp transient set <key> <value> [expiration]
   or: wp transient delete <key>
   or: wp transient type
EOB
	    );	
	}
}