<?php

namespace WP_CLI\Bootstrap;

/**
 * Class InitializeLogger.
 *
 * Initialize the logger through the `WP_CLI\Runner` object.
 *
 * @package WP_CLI\Bootstrap
 */
final class InitializeLogger implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @return void
	 */
	public function process() {
		$this->declare_loggers();
		$runner = new RunnerInstance();
		$runner()->init_logger();
	}

	/**
	 * Load the class declarations for the loggers.
	 */
	private function declare_loggers() {
		$logger_dir = WP_CLI_ROOT . '/php/WP_CLI/Loggers';
		$iterator   = new \DirectoryIterator( $logger_dir );

		// Make sure the base class is declared first.
		include_once "$logger_dir/Base.php";

		foreach ( $iterator as $filename ) {
			if ( '.php' !== substr( $filename, - 4 ) ) {
				continue;
			}

			include_once "$logger_dir/$filename";
		}
	}
}
