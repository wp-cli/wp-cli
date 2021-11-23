<?php

namespace WP_CLI\Context;

use WP_CLI;
use WP_CLI\Context;
use WP_CLI\ContextManager;

/**
 * Context which switches to other contexts automatically based on conditions.
 */
final class Auto implements Context {

	/**
	 * Array of commands to intercept.
	 *
	 * @var array<array>
	 */
	const COMMANDS_TO_RUN_AS_ADMIN = [
		[ 'plugin' ],
		[ 'theme' ],
	];

	/**
	 * Context manager instance to use.
	 *
	 * @var ContextManager
	 */
	private $context_manager;

	/**
	 * Instantiate an Auto object.
	 *
	 * @param ContextManager $context_manager Context manager instance to use.
	 */
	public function __construct( ContextManager $context_manager ) {
		$this->context_manager = $context_manager;
	}

	/**
	 * Process the context to set up the environment correctly.
	 *
	 * @param array $config Associative array of configuration data.
	 * @return void
	 * @throws WP_CLI\ExitException If an invalid context was deduced.
	 */
	public function process( $config ) {
		$config['context'] = $this->deduce_best_context();

		$this->context_manager->switch_context( $config );
	}

	/**
	 * Deduce the best context to run the current command in.
	 *
	 * @return string Context to use.
	 */
	private function deduce_best_context() {
		if ( $this->is_command_to_run_as_admin() ) {
			return Context::ADMIN;
		}

		return Context::CLI;
	}

	/**
	 * Check whether the current WP-CLI command is amongst those we want to
	 * run as admin.
	 *
	 * @return bool Whether the current command should be run as admin.
	 */
	private function is_command_to_run_as_admin() {
		$command = WP_CLI::get_runner()->arguments;

		foreach ( self::COMMANDS_TO_RUN_AS_ADMIN as $command_to_run_as_admin ) {
			if (
				array_slice( $command, 0, count( $command_to_run_as_admin ) )
				===
				$command_to_run_as_admin
			) {
				WP_CLI::debug(
					'Detected a command to be intercepted: '
					. implode( ' ', $command ),
					Context::DEBUG_GROUP
				);
				return true;
			}
		}

		return false;
	}
}
