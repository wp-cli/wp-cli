<?php

/**
 * Manages WordPress maintenance mode.
 *
 * ## EXAMPLES
 *
 *     $ wp maintenance on
 *     Success: Enabled Maintenance mode.
 *
 *     $ wp maintenance off
 *     Success: Disabled Maintenance mode.
 *
 *     $ wp maintenance status
 *     Success: Maintenance mode is on.
 *
 * @when after_wp_load
 */
class Maintenance_Command extends WP_CLI_Command {


	private static $instance;

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			WP_Filesystem(); // Initialises WordPress Filesystem classes.
			if ( ! class_exists( 'WP_Upgrader' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}
			self::$instance = new WP_Upgrader();
		}
		return self::$instance;
	}

	/**
	 * Enables Maintenance mode.
	 *
	 * [--force]
	 * : Force the operation.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp maintenance on
	 *     Success: Enabled Maintenance mode.
	 *
	 * @subcommand on
	 */
	public function on( $_, $assoc_args ) {
		if ( $this->maintenance_mode_status() && ! WP_CLI\Utils\get_flag_value( $assoc_args, 'force' ) ) {
			WP_CLI::error( 'Maintenance mode already enabled.' );
		}

		self::get_instance()->maintenance_mode( true );
		WP_CLI::success( 'Enabled Maintenance mode.' );
	}

	/**
	 * Disables Maintenance mode.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp maintenance off
	 *     Success: Disabled Maintenance mode.
	 *
	 * @subcommand off
	 */
	public function off() {
		if ( $this->maintenance_mode_status() ) {
			self::get_instance()->maintenance_mode( false );
			WP_CLI::success( 'Disabled Maintenance mode.' );
		} else {
			WP_CLI::error( 'Maintenance mode already disabled.' );
		}
	}

	/**
	 * Disables Maintenance mode.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp maintenance status
	 *     Success: Maintenance mode is on.
	 *
	 * @subcommand status
	 */
	public function status() {
		if ( $this->maintenance_mode_status() ) {
			WP_CLI::success( 'Maintenance mode is on.' );
		} else {
			WP_CLI::success( 'Maintenance mode is off.' );
		}
	}

	/**
	 * Return status of maintenance mode.
	 *
	 * @return bool
	 */
	private function maintenance_mode_status() {
		WP_Filesystem();

		global $wp_filesystem;

		if ( $wp_filesystem->exists( $wp_filesystem->abspath() . '.maintenance' ) ) {
			return true;
		} else {
			return false;
		}
	}
}
