<?php

namespace WP_CLI\Bootstrap;

/**
 * Class InitializeColorization.
 *
 * Initialize the colorization through the `WP_CLI\Runner` object.
 *
 * @package WP_CLI\Bootstrap
 */
final class InitializeColorization implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @return void
	 */
	public function process() {
		$runner = new RunnerInstance();
		$runner()->init_colorization();
	}
}
