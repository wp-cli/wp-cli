<?php

WP_CLI::addCommand('option', 'Option_Command');

/**
 * Implement option command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Option_Command extends WP_CLI_Command {

	/**
	 * Add an option
	 *
	 * @param array $args
	 **/
	public function add( $args ) {
		if ( count( $args ) < 2 ) {
			WP_CLI::line( "usage: wp option add <option-name> <option-value>" );
			exit;
		}

		list( $key, $value ) = $args;

		if ( !add_option( $key, $value ) ) {
			WP_CLI::error( "Could not add option '$key'. Does it already exist?" );
		}
	}

	/**
	 * Update an option
	 *
	 * @param array $args
	 **/
	public function update( $args ) {
		if ( count( $args ) < 2 ) {
			WP_CLI::line( "usage: wp option update <option-name> <option-value>" );
			exit;
		}

		list( $key, $value ) = $args;

		if ( $value === get_option( $key ) )
			return;

		if ( !update_option( $key, $value ) ) {
			WP_CLI::error( "Could not update option '$key'." );
		}
	}

	/**
	 * Delete an option
	 *
	 * @param array $args
	 **/
	public function delete( $args ) {
		if ( empty( $args ) ) {
			WP_CLI::line( "usage: wp option get <option-name>" );
			exit;
		}

		list( $key ) = $args;

		if ( !delete_option( $key ) ) {
			WP_CLI::error( "Could not delete '$key' option. Does it exist?" );
		}
	}

	/**
	 * Get an option
	 *
	 * @param array $args
	 **/
	public function get( $args ) {
		if ( empty( $args ) ) {
			WP_CLI::line( "usage: wp option get <option-name>" );
			exit;
		}

		list( $key ) = $args;

		$value = get_option( $key );

		if ( false === $value )
			return;

		if ( is_array( $value ) || is_object( $value ) ) {
			echo var_export( $value ) . "\n";
		} else {
			echo $value . "\n";
		}
	}

	/**
	 * Help function for this command
	 */
	public static function help() {
		WP_CLI::line( <<<EOB
usage: wp option get <option-name>
   or: wp option add <option-name> <option-value>
   or: wp option update <option-name> <option-value>
   or: wp option delete <option-name>
EOB
	    );
	}
}