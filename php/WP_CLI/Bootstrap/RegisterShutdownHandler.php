<?php

namespace WP_CLI\Bootstrap;

use WP_CLI\ShutdownHandler;

/**
 * Class RegisterShutdownHandler.
 *
 * Registers the shutdown handler to detect incomplete execution and suggest workarounds.
 *
 * @package WP_CLI\Bootstrap
 */
final class RegisterShutdownHandler implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		ShutdownHandler::register();

		return $state;
	}
}
