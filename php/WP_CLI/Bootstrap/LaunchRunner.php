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
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		$runner = new RunnerInstance();

		/**
		 * @var \WP_CLI\ContextManager $context_manager
		 */
		$context_manager = $state->getValue( 'context_manager' );

		$runner()->register_context_manager( $context_manager );

		$runner()->start();

		return $state;
	}
}
