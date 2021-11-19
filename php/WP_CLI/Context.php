<?php

namespace WP_CLI;

/**
 * Context that can be selected in order to run commands within a properly
 * set-up environment.
 */
interface Context {

	const ADMIN    = 'admin';
	const AUTO     = 'auto';
	const CLI      = 'cli';
	const FRONTEND = 'frontend';

	/**
	 * Debugging group to use for all context-related debug messages.
	 *
	 * @var string
	 */
	const DEBUG_GROUP = 'context';

	/**
	 * Process the context to set up the environment correctly.
	 *
	 * @param array $config Associative array of configuration data.
	 * @return void
	 */
	public function process( $config );
}
