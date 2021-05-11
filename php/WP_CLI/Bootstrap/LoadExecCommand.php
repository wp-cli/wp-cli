<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;

/**
 * Class LoadExtraCommand.
 *
 * Loads a command that was passed through the `--exec=<php-code>` option.
 *
 * @package WP_CLI\Bootstrap
 */
final class LoadExecCommand implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		if ( $state->getValue( BootstrapState::IS_PROTECTED_COMMAND, false ) ) {
			return $state;
		}

		$runner = new RunnerInstance();
		if ( ! isset( $runner()->config['exec'] ) ) {
			return $state;
		}

		foreach ( $runner()->config['exec'] as $php_code ) {
			eval( $php_code ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		}

		return $state;
	}
}
