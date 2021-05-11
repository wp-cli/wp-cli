<?php

namespace WP_CLI\Bootstrap;

/**
 * Class DefineProtectedCommands.
 *
 * Define the commands that are "protected", meaning that they shouldn't be able
 * to break due to extension code.
 *
 * @package WP_CLI\Bootstrap
 */
final class DefineProtectedCommands implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		$commands        = $this->get_protected_commands();
		$current_command = $this->get_current_command();

		foreach ( $commands as $command ) {
			if ( 0 === strpos( $current_command, $command ) ) {
				$state->setValue( BootstrapState::IS_PROTECTED_COMMAND, true );
			}
		}

		return $state;
	}

	/**
	 * Get the list of protected commands.
	 *
	 * @return array
	 */
	private function get_protected_commands() {
		return [
			'cli info',
			'package',
		];
	}

	/**
	 * Get the current command as a string.
	 *
	 * @return string Current command to be executed.
	 */
	private function get_current_command() {
		$runner = new RunnerInstance();

		return implode( ' ', (array) $runner()->arguments );
	}
}
