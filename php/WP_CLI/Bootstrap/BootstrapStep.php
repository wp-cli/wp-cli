<?php

namespace WP_CLI\Bootstrap;

/**
 * Interface BootstrapStep.
 *
 * Represents a single bootstrapping step that can be processed.
 *
 * @package WP_CLI\Bootstrap
 */
interface BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @return void
	 */
	public function process();
}
