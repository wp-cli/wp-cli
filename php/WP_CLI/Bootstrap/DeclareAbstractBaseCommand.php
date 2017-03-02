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
	 * @return void
	 */
	public function process() {
		require_once WP_CLI_ROOT . '/php/class-wp-cli-command.php';
	}
}
