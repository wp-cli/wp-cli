<?php

namespace WP_CLI\Bootstrap;

use Exception;
use WP_CLI;

/**
 * Abstract class AutoloaderStep.
 *
 * Abstract base class for steps that include an autoloader.
 *
 * @package WP_CLI\Bootstrap
 */
abstract class AutoloaderStep implements BootstrapStep {

	/**
	 * Store state for subclasses to have access.
	 *
	 * @var BootstrapState
	 */
	protected $state;

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		$this->state = $state;

		$found_autoloader = false;
		$autoloader_paths = $this->get_autoloader_paths();

		if ( false === $autoloader_paths ) {
			// Skip this autoload step.
			return $state;
		}

		foreach ( $autoloader_paths as $autoloader_path ) {
			if ( is_readable( $autoloader_path ) ) {
				try {
					WP_CLI::debug(
						sprintf(
							'Loading detected autoloader: %s',
							$autoloader_path
						),
						'bootstrap'
					);
					require $autoloader_path;
					$found_autoloader = true;
				} catch ( Exception $exception ) {
					WP_CLI::warning(
						"Failed to load autoloader '{$autoloader_path}'. Reason: "
						. $exception->getMessage()
					);
				}
			}
		}

		if ( ! $found_autoloader ) {
			$this->handle_failure();
		}

		return $this->state;
	}

	/**
	 * Get the name of the custom vendor folder as set in `composer.json`.
	 *
	 * @return string|false Name of the custom vendor folder or false if none.
	 */
	protected function get_custom_vendor_folder() {
		$maybe_composer_json = WP_CLI_ROOT . '/../../../composer.json';
		if ( ! is_readable( $maybe_composer_json ) ) {
			return false;
		}

		$composer = json_decode( file_get_contents( $maybe_composer_json ) );

		if ( ! empty( $composer->config )
			&& ! empty( $composer->config->{'vendor-dir'} )
		) {
			return $composer->config->{'vendor-dir'};
		}

		return false;
	}

	/**
	 * Handle the failure to find an autoloader.
	 *
	 * @return void
	 */
	protected function handle_failure() { }

	/**
	 * Get the autoloader paths to scan for an autoloader.
	 *
	 * @return string[]|false Array of strings with autoloader paths, or false
	 *                        to skip.
	 */
	abstract protected function get_autoloader_paths();
}
