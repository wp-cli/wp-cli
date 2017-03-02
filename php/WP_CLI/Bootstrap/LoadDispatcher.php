<?php

namespace WP_CLI\Bootstrap;

/**
 * Class LoadDispatcher.
 *
 * Loads the dispatcher that will dispatch command names to file locations.
 *
 * @package WP_CLI\Bootstrap
 */
final class LoadDispatcher implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @return void
	 */
	public function process() {
		require_once WP_CLI_ROOT . '/php/dispatcher.php';
	}
}
