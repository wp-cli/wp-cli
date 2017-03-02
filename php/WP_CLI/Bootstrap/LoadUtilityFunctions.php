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
	 * @return void
	 */
	public function process() {
		require_once WP_CLI_ROOT . '/php/utils.php';
	}
}
