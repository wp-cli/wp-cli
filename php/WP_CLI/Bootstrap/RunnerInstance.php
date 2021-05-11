<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;
use WP_CLI\Runner;

/**
 * Class RunnerInstance.
 *
 * Convenience class for steps that make use of the `WP_CLI\Runner` object.
 *
 * @package WP_CLI\Bootstrap
 */
final class RunnerInstance {

	/**
	 * Return an instance of the `WP_CLI\Runner` object.
	 *
	 * Includes necessary class files first as needed.
	 *
	 * @return Runner
	 */
	public function __invoke() {
		if ( ! class_exists( 'WP_CLI\Runner' ) ) {
			require_once WP_CLI_ROOT . '/php/WP_CLI/Runner.php';
		}

		if ( ! class_exists( 'WP_CLI\Configurator' ) ) {
			require_once WP_CLI_ROOT . '/php/WP_CLI/Configurator.php';
		}

		return WP_CLI::get_runner();
	}
}
