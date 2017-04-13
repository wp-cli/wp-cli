<?php

namespace WP_CLI\Bootstrap;

/**
 * Class LoadUtilityFunctions.
 *
 * Loads the functions available through `WP_CLI\Utils`.
 *
 * @package WP_CLI\Bootstrap
 */
final class LoadUtilityFunctions implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		require_once WP_CLI_ROOT . '/php/utils.php';

		return $state;
	}
}
