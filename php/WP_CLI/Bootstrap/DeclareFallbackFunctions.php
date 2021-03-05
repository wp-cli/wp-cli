<?php

namespace WP_CLI\Bootstrap;

/**
 * Class DeclareFallbackFunctions.
 *
 * Declares functions that might have been disabled but are required.
 *
 * @package WP_CLI\Bootstrap
 */
final class DeclareFallbackFunctions implements BootstrapStep {
	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		include __DIR__ . '/../../fallback-functions.php';

		return $state;
	}
}
