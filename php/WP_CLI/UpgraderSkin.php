<?php

namespace WP_CLI;

use WP_CLI;
use WP_Upgrader_Skin;

/**
 * A Upgrader Skin for WordPress that only generates plain-text
 *
 * @package wp-cli
 */
class UpgraderSkin extends WP_Upgrader_Skin {

	/**
	 * @var \stdClass|null
	 */
	public $api;

	/**
	 * @return void
	 */
	public function header() {}

	/**
	 * @return void
	 */
	public function footer() {}

	/**
	 * @return void
	 */
	public function bulk_header() {}

	/**
	 * @return void
	 */
	public function bulk_footer() {}

	/**
	 * Show error message.
	 *
	 * @param string|\WP_Error $error Error message.
	 *
	 * @return void
	 */
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

	/**
	 * @param string $string
	 * @param mixed  ...$args Optional text replacements.
	 */
	public function feedback( $string, ...$args ) {
		$args_array = [];
		foreach ( $args as $arg ) {
			$args_array[] = $args;
		}

		$this->process_feedback( $string, $args );
	}

	/**
	 * Process the feedback collected through the compat indirection.
	 *
	 * @param string       $string String to use as feedback message.
	 * @param array<mixed> $args Array of additional arguments to process.
	 * @return void
	 */
	public function process_feedback( $string, $args ) {

		if ( 'parent_theme_prepare_install' === $string && is_object( $this->api ) ) {
			WP_CLI::get_http_cache_manager()->whitelist_package( $this->api->download_link, 'theme', $this->api->slug, $this->api->version );
		}

		if ( isset( $this->upgrader->strings[ $string ] ) ) {
			$string = $this->upgrader->strings[ $string ];
		}

		if ( ! empty( $args ) && is_array( $args ) && strpos( $string, '%' ) !== false ) {
			$string = vsprintf(
				$string,
				array_values(
					array_map(
						function ( $v ) {
							return is_scalar( $v ) ? (string) $v : '';
						},
						$args
					)
				)
			);
		}

		if ( empty( $string ) ) {
			return;
		}

		$string = str_replace( '&#8230;', '...', Utils\strip_tags( $string ) );
		$string = html_entity_decode( $string, ENT_QUOTES, get_bloginfo( 'charset' ) );

		WP_CLI::log( $string );
	}
}
