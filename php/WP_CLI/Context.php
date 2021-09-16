<?php

namespace WP_CLI;

use WP_CLI;

final class Context {

	const ADMIN    = 'admin';
	const AUTO     = 'auto';
	const CLI      = 'cli';
	const FRONTEND = 'frontend';

	const KNOWN_CONTEXTS = [
		self::ADMIN,
		self::AUTO,
		self::CLI,
		self::FRONTEND,
	];

	/**
	 * Debugging group to use for this class.
	 *
	 * @var string
	 */
	const DEBUG_GROUP = 'context';

	/**
	 * Array of commands to intercept.
	 *
	 * @var array<array>
	 */
	const COMMANDS_TO_RUN_AS_ADMIN = [
		[ 'plugin' ],
		[ 'theme' ],
	];

	/**
	 * Sets the context in which to run WP-CLI.
	 *
	 * @param array $config Associative array of configuration data.
	 */
	public function process( $config ) {
		$context = isset( $config['context'] ) ? $config['context'] : 'cli';

		WP_CLI::debug( "Using context {$context}", self::DEBUG_GROUP );

		if ( $context === self::AUTO ) {
			$context = $this->deduce_best_context();
		}

		switch ( $context ) {
			case self::ADMIN:
				$this->fake_admin_request();
				break;
			case self::FRONTEND:
				// TODO: Simulate templated frontend request.
				break;
			case self::CLI:
			default:
				break;
		}
	}

	/**
	 * Fake the environment of an administration request running on the WP
	 * backend.
	 */
	private function fake_admin_request() {
		if ( defined( 'WP_ADMIN' ) ) {
			if ( ! WP_ADMIN ) {
				WP_CLI::warning( 'Could not fake admin request.' );
			}

			return;
		}

		WP_CLI::debug( 'Faking an admin request', 'context' );

		// Define `WP_ADMIN` as being true. This causes the helper method
		// `is_admin()` to return true as well.
		define( 'WP_ADMIN', true );

		// Set a fake entry point to ensure wp-includes/vars.php does not throw
		// notices/errors. This will be reflected in the global `$pagenow`
		// variable being set to 'wp-cli-fake-admin-file.php'.
		$_SERVER['PHP_SELF'] = '/wp-admin/wp-cli-fake-admin-file.php';

		// Bootstrap the WordPress administration area.
		WP_CLI::add_wp_hook(
			'admin_init',
			function () {
				global $wp_db_version, $_wp_submenu_nopriv;

				// Make sure we don't trigger a DB upgrade as that tries to redirect
				// the page.
				$wp_db_version = (int) get_option( 'db_version' );

				// Ensure WP does not iterate over an undefined variable in
				// `user_can_access_admin_page()`.
				if ( ! isset( $_wp_submenu_nopriv ) ) {
					$_wp_submenu_nopriv = [];
				}

				$this->log_in_as_admin_user();

				require_once ABSPATH . 'wp-admin/admin.php';
			},
			0
		);
	}

	/**
	 * Deduce the best context to run the current command in.
	 *
	 * @return string Context to use.
	 */
	private function deduce_best_context() {
		if ( $this->is_command_to_run_as_admin() ) {
			return self::ADMIN;
		}

		return self::CLI;
	}

	/**
	 * Check whether the current WP-CLI command is amongst those we want to
	 * run as admin.
	 *
	 * @return bool Whether the current command should be run as admin.
	 */
	private function is_command_to_run_as_admin() {
		 $command = WP_CLI::get_runner()->arguments;

		foreach ( self::COMMANDS_TO_RUN_AS_ADMIN as $command_to_run_as_admin ) {
			if ( array_slice( $command, 0, count( $command_to_run_as_admin ) ) === $command_to_run_as_admin ) {
				WP_CLI::debug( 'Detected a command to be intercepted: ' . implode( ' ', $command ), self::DEBUG_GROUP );
				return true;
			}
		}

		return false;
	}
}
