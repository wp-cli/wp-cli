<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;
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

		$contexts = [
			Context::CLI      => new Context\Cli(),
			Context::ADMIN    => new Context\Admin(),
			Context::FRONTEND => new Context\Frontend(),
			Context::AUTO     => new Context\Auto( $context_manager ),
		];

		$contexts = WP_CLI::do_hook( 'before_registering_contexts', $contexts );

		foreach ( $contexts as $name => $implementation ) {
			$context_manager->register_context( $name, $implementation );
		}

		$state->setValue( 'context_manager', $context_manager );

		return $state;
	}
}
