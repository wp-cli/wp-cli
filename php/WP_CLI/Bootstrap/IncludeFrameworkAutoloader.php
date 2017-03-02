<?php

namespace WP_CLI\Bootstrap;

use WP_CLI\Autoloader;

/**
 * Class IncludeFrameworkAutoloader.
 *
 * Registers the main autoloader that comes with the WP_CLI framework.
 *
 * @package WP_CLI\Bootstrap
 */
final class IncludeFrameworkAutoloader implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @return void
	 */
	public function process() {
		if ( ! class_exists( 'WP_CLI\Autoloader' ) ) {
			require_once WP_CLI_ROOT . '/php/WP_CLI/Autoloader.php';
		}

		$autoloader = new Autoloader();
		$autoloader->add_namespace( 'WP_CLI', WP_CLI_ROOT . '/php/WP_CLI' )
		           ->register();
	}
}
