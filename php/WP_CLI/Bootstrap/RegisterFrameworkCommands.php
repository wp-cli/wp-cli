<?php

namespace WP_CLI\Bootstrap;

/**
 * Class RegisterFrameworkCommands.
 *
 * Register the commands that are directly included with the framework.
 *
 * @package WP_CLI\Bootstrap
 */
final class RegisterFrameworkCommands implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @return void
	 */
	public function process() {
		$cmd_dir = WP_CLI_ROOT . '/php/commands';

		$iterator = new \DirectoryIterator( $cmd_dir );

		foreach ( $iterator as $filename ) {
			if ( '.php' !== substr( $filename, - 4 ) ) {
				continue;
			}

			try {
				include_once "$cmd_dir/$filename";
			} catch ( \Exception $exception ) {
				\WP_CLI::warning(
					"Could not add command {$cmd_dir}/{$filename}. Reason: " . $exception->getMessage(),
					'bootstrap'
				);
			}
		}
	}
}
