<?php

namespace WP_CLI\Bootstrap;

/**
 * Class DeclareAbstractBaseCommand.
 *
 * Declares the abstract `WP_CLI_Command` base class.
 *
 * @package WP_CLI\Bootstrap
 */
final class DeclareAbstractBaseCommand implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		require_once WP_CLI_ROOT . '/php/class-wp-cli-command.php';

		return $state;
	}
}
