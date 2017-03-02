<?php

namespace WP_CLI\Bootstrap;

/**
 * Class LaunchRunner.
 *
 * Kick off the Runner object that starts the actual commands.
 *
 * @package WP_CLI\Bootstrap
 */
final class LaunchRunner implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @return void
	 */
	public function process() {
		$runner = new RunnerInstance();
		$runner()->start();
	}
}
