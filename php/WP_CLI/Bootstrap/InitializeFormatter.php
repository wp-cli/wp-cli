<?php

namespace WP_CLI\Bootstrap;

/**
 * Class InitializeFormatter.
 *
 * Registers the built-in format handlers for the Formatter class.
 *
 * The Formatter also registers them on demand, so this step only makes sure they
 * are in place early on. Formatter::register_builtin_formats() is idempotent.
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
