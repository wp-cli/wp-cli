<?php

namespace WP_CLI\Bootstrap;

/**
 * Class RegisterDeferredCommands.
 *
 * Registers the deferred commands that for which no parent was registered yet.
 * This is necessary, because we can have sub-commands that have no direct
 * parent, like `wp network meta`.
 *
 * @package WP_CLI\Bootstrap
 */
final class RegisterDeferredCommands implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		$deferred_additions = \WP_CLI::get_deferred_additions();

		foreach ( $deferred_additions as $name => $addition ) {
			\WP_CLI::add_command(
				$name,
				$addition['callable'],
				$addition['args']
			);
		}

		return $state;
	}
}
