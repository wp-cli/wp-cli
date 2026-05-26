<?php

namespace WP_CLI\Bootstrap;

/**
 * Class InitializeFormatter.
 *
 * Registers the built-in format handlers for the Formatter class.
 *
 * @package WP_CLI\Bootstrap
 */
final class InitializeFormatter implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		\WP_CLI\Formatter::register_builtin_formats();

		return $state;
	}
}
