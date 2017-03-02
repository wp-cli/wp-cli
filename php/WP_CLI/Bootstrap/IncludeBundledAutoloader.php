<?php

namespace WP_CLI\Bootstrap;

/**
 * Class IncludeBundledAutoloader.
 *
 * Loads the bundled autoloader that is provided through the `composer.json`
 * file.
 *
 * @package WP_CLI\Bootstrap
 */
final class IncludeBundledAutoloader extends AutoloaderStep {

	/**
	 * Get the vendor paths to scan for an autoloader.
	 *
	 * @return string[]|false Array of strings with vendor paths, or false to
	 *                        skip.
	 */
	protected function get_vendor_paths() {
		if ( $this->is_inside_phar() ) {
			// Skip main autoloader now, so that it will be loaded during the
			// `IncludePHARAutoloader` step only.
			return false;
		}

		$vendor_paths = array(
			// Part of a larger project / installed via Composer (preferred).
			WP_CLI_ROOT . '/../../../vendor',
			// Top-level project / installed as Git clone.
			WP_CLI_ROOT . '/vendor',
		);

		$maybe_composer_json = WP_CLI_ROOT . '/../../../composer.json';
		if ( ! is_readable( $maybe_composer_json ) ) {
			return $vendor_paths;
		}

		$composer = json_decode( file_get_contents( $maybe_composer_json ) );

		if ( ! empty( $composer->config )
		     && ! empty( $composer->config->{'vendor-dir'} )
		) {
			array_unshift(
				$vendor_paths,
				WP_CLI_ROOT . '/../../../' . $composer->config->{'vendor-dir'}
			);
		}

		return $vendor_paths;
	}

	/**
	 * Handle the failure to find an autoloader.
	 *
	 * @return void
	 */
	protected function handle_failure() {
		fputs(
			STDERR,
			"Internal error: Can't find Composer autoloader.\nTry running: composer install\n"
		);
		exit( 3 );
	}
}
