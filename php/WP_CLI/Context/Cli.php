<?php

namespace WP_CLI\Context;

use WP_CLI;
use WP_CLI\Context;

/**
 * Default WP-CLI context.
 */
final class Cli implements Context {

	/**
	 * Process the context to set up the environment correctly.
	 *
	 * @param array $config Associative array of configuration data.
	 *
	 * @return void
	 */
	public function process( $config ) {
		// Nothing needs to be done for now, as this is the default.
	}
}
