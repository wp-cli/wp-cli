<?php

namespace WP_CLI\Bootstrap;

use WP_CLI\Context;
use WP_CLI\ContextManager;

/**
 * Class InitializeContexts.
 *
 * @package WP_CLI\Bootstrap
 */
final class InitializeContexts implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		$context_manager = new ContextManager();

		$default_contexts = [
			Context::CLI      => new Context\Cli(),
			Context::ADMIN    => new Context\Admin(),
			Context::FRONTEND => new Context\Frontend(),
			Context::AUTO     => new Context\Auto( $context_manager ),
		];

		foreach ( $default_contexts as $name => $implementation ) {
			$context_manager->register_context( $name, $implementation );
		}

		$state->setValue( 'context_manager', $context_manager );

		return $state;
	}
}
