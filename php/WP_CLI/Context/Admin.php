<?php

namespace WP_CLI\Context;

use WP_CLI;
use WP_CLI\Context;
use WP_Session_Tokens;

/**
 * Context which simulates the administrator backend.
 */
final class Admin implements Context {

	/**
	 * Process the context to set up the environment correctly.
	 *
	 * @param array $config Associative array of configuration data.
	 * @return void
	 */
	public function process( $config ) {
		if ( defined( 'WP_ADMIN' ) ) {
			if ( ! WP_ADMIN ) {
				WP_CLI::warning( 'Could not fake admin request.' );
			}

			return;
		}

		WP_CLI::debug( 'Faking an admin request', Context::DEBUG_GROUP );

		// Define `WP_ADMIN` as being true. This causes the helper method
		// `is_admin()` to return true as well.
		define( 'WP_ADMIN', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound

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
				$wp_db_version = (int) get_option( 'db_version' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

				// Ensure WP does not iterate over an undefined variable in
				// `user_can_access_admin_page()`.
				if ( ! isset( $_wp_submenu_nopriv ) ) {
					$_wp_submenu_nopriv = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				}

				$this->log_in_as_admin_user();

				require_once ABSPATH . 'wp-admin/admin.php';
			},
			0
		);
	}

	/**
	 * Ensure the current request is done under a logged-in administrator
	 * account.
	 *
	 * A lot of premium plugins/themes have their custom update routines locked
	 * behind an is_admin() call.
	 *
	 * @return void
	 */
	private function log_in_as_admin_user() {
		// TODO: Add logic to find an administrator user.
		$admin_user_id = 1;

		wp_set_current_user( $admin_user_id );

		$expiration = time() + DAY_IN_SECONDS;
		$manager    = WP_Session_Tokens::get_instance( $admin_user_id );
		$token      = $manager->create( $expiration );

		$_COOKIE[ AUTH_COOKIE ] = wp_generate_auth_cookie(
			$admin_user_id,
			$expiration,
			'auth',
			$token
		);

		$_COOKIE[ SECURE_AUTH_COOKIE ] = wp_generate_auth_cookie(
			$admin_user_id,
			$expiration,
			'secure_auth',
			$token
		);
	}
}
