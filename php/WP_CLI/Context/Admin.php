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
			'init',
			function () {
				$this->log_in_as_admin_user();
				$this->load_admin_environment();
			},
			defined( 'PHP_INT_MIN' ) ? PHP_INT_MIN : -2147483648, // phpcs:ignore PHPCompatibility.Constants.NewConstants.php_int_minFound
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

		$_COOKIE[ AUTH_COOKIE ] = wp_generate_auth_cookie(
			$admin_user_id,
			$expiration,
			'auth'
		);

		$_COOKIE[ SECURE_AUTH_COOKIE ] = wp_generate_auth_cookie(
			$admin_user_id,
			$expiration,
			'secure_auth'
		);
	}

	/**
	 * Load the admin environment.
	 *
	 * This tries to load `wp-admin/admin.php` while trying to avoid issues
	 * like re-loading the wp-config.php file (which redeclares constants).
	 *
	 * To make this work across WordPress versions, we use the actual file and
	 * modify it on-the-fly.
	 *
	 * @return void
	 */
	private function load_admin_environment() {
		global $hook_suffix, $pagenow, $wp_db_version, $_wp_submenu_nopriv;

		if ( ! isset( $hook_suffix ) ) {
			$hook_suffix = 'index'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		// Make sure we don't trigger a DB upgrade as that tries to redirect
		// the page.
		$wp_db_version = (int) get_option( 'db_version' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		// Ensure WP does not iterate over an undefined variable in
		// `user_can_access_admin_page()`.
		if ( ! isset( $_wp_submenu_nopriv ) ) {
			$_wp_submenu_nopriv = []; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$admin_php_file = file_get_contents( ABSPATH . 'wp-admin/admin.php' );

		// First we remove the opening and closing PHP tags.
		$admin_php_file = preg_replace( '/^<\?php\s+/', '', $admin_php_file );
		$admin_php_file = preg_replace( '/\s+\?>$/', '', $admin_php_file );

		// Then we remove the loading of either wp-config.php or wp-load.php.
		$admin_php_file = preg_replace( '/^\s*(?:include|require).*[\'"]\/?wp-(?:load|config)\.php[\'"]\s*\)?;$/m', '', $admin_php_file );

		// We also remove the authentication redirect.
		$admin_php_file = preg_replace( '/^\s*auth_redirect\(\);$/m', '', $admin_php_file );

		// Finally, we avoid sending headers.
		$admin_php_file   = preg_replace( '/^\s*nocache_headers\(\);$/m', '', $admin_php_file );
		$_GET['noheader'] = true;

		eval( $admin_php_file ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
	}
}
