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
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state );
}
