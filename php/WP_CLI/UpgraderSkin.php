<?php

namespace WP_CLI;

use WP_CLI;
use WP_CLI\Utils;
use WP_Upgrader_Skin;

/**
 * A Upgrader Skin for WordPress that only generates plain-text
 *
 * @package wp-cli
 */
class UpgraderSkin extends WP_Upgrader_Skin {

	public $api;

	public function header() {}
	public function footer() {}
	public function bulk_header() {}
	public function bulk_footer() {}

	public function error( $error ) {
		if ( ! $error ) {
			return;
		}

		if ( is_string( $error ) && isset( $this->upgrader->strings[ $error ] ) ) {
			$error = $this->upgrader->strings[ $error ];
		}

		// TODO: show all errors, not just the first one
		WP_CLI::warning( $error );
	}

	public function feedback( $string ) {

		if ( 'parent_theme_prepare_install' === $string ) {
			WP_CLI::get_http_cache_manager()->whitelist_package( $this->api->download_link, 'theme', $this->api->slug, $this->api->version );
		}

		if ( isset( $this->upgrader->strings[ $string ] ) ) {
			$string = $this->upgrader->strings[ $string ];
		}

		if ( strpos( $string, '%' ) !== false ) {
			// Only looking at the arguments from the second one onwards, so this is "safe".
			// phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.Changed
			$args = func_get_args();
			$args = array_splice( $args, 1 );
			if ( ! empty( $args ) ) {
				$string = vsprintf( $string, $args );
			}
		}

		if ( empty( $string ) ) {
			return;
		}

		$string = str_replace( '&#8230;', '...', Utils\strip_tags( $string ) );
		$string = html_entity_decode( $string, ENT_QUOTES, get_bloginfo( 'charset' ) );

		WP_CLI::log( $string );
	}
}

