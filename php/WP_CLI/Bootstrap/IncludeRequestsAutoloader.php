<?php

namespace WP_CLI\Bootstrap;

use WP_CLI\RequestsLibrary;

/**
 * Class IncludeRequestsAutoloader.
 *
 * Registers a deferred autoloader for the bundled Requests library during
 * bootstrap so that commands which do not load WordPress do not probe the
 * filesystem for a WordPress installation or load Requests files from the
 * working directory.
 *
 * When WordPress is actually loaded by `Runner::load_wordpress()`, WordPress
 * Core's own Requests library is preferred if available.
 *
 * @package WP_CLI\Bootstrap
 */
final class IncludeRequestsAutoloader implements BootstrapStep {

	/**
	 * Requests is being used from the WordPress installation.
	 *
	 * @var string
	 */
	const FROM_WP_CORE = 'wp-core';

	/**
	 * Requests is being used from the WP-CLI dependencies.
	 *
	 * @var string
	 */
	const FROM_WP_CLI = 'wp-cli';

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		RequestsLibrary::register_deferred_autoloader();

		return $state;
	}
}
