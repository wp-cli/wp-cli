<?php
/**
 * Minimal stand-in for WordPress's WP_Http redirect check, so that
 * WpHttpCacheManager::prefetch() can be unit-tested without WordPress loaded.
 */

require_once __DIR__ . '/wp-http-validate-url-stub.php';

if ( ! class_exists( 'WP_Http' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
	class WP_Http {
		/**
		 * @param string $location
		 * @return void
		 * @throws \WpOrg\Requests\Exception
		 */
		public static function validate_redirects( $location ) {
			if ( ! wp_http_validate_url( $location ) ) {
				throw new \WpOrg\Requests\Exception( 'A valid URL was not provided.', 'wp_http.redirect_failed_validation' );
			}
		}
	}
}
