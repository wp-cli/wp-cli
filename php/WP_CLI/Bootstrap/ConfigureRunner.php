<?php

namespace WP_CLI\Bootstrap;

/**
 * Class ConfigureRunner.
 *
 * Initialize the configuration for the `WP_CLI\Runner` object.
 *
 * @package WP_CLI\Bootstrap
 */
final class ConfigureRunner implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @return void
	 */
	public function process() {
		$runner = new RunnerInstance();
		$runner()->init_config();
	}
}
