<?php
/**
 * Minimal stand-in for WordPress's wp_http_validate_url(), so that
 * WpHttpCacheManager::prefetch() can be unit-tested without WordPress loaded.
 * Any URL on the host "internal.example" counts as unsafe.
 */

if ( ! function_exists( 'wp_http_validate_url' ) ) {
	/**
	 * @param string $url
	 * @return string|false
	 */
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	function wp_http_validate_url( $url ) {
		return 'internal.example' === \WP_CLI\Utils\parse_url( $url, PHP_URL_HOST ) ? false : $url;
	}
}
