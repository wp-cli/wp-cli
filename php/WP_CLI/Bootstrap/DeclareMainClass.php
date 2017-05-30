<?php

namespace WP_CLI\Bootstrap;

/**
 * Class DeclareMainClass.
 *
 * Declares the main `WP_CLI` class.
 *
 * @package WP_CLI\Bootstrap
 */
final class DeclareMainClass implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		require_once WP_CLI_ROOT . '/php/class-wp-cli.php';

		return $state;
	}
}
