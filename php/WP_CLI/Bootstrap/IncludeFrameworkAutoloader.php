<?php

namespace WP_CLI\Bootstrap;

use WP_CLI\Autoloader;

/**
 * Class IncludeFrameworkAutoloader.
 *
 * Loads the framework autoloader through an autolaoder separate from the
 * Composer one, to avoid coupling the loading of the framework with bundled
 * commands.
 *
 * This only contains classes for the framework.
 *
 * @package WP_CLI\Bootstrap
 */
final class IncludeFrameworkAutoloader implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		if ( ! class_exists( 'WP_CLI\Autoloader' ) ) {
			require_once WP_CLI_ROOT . '/php/WP_CLI/Autoloader.php';
		}

		$autoloader = new Autoloader();

		$mappings = [
			'WP_CLI'                   => WP_CLI_ROOT . '/php/WP_CLI',
			'cli'                      => WP_CLI_VENDOR_DIR . '/wp-cli/php-cli-tools/lib/cli',
			'Requests'                 => WP_CLI_VENDOR_DIR . '/rmccue/requests/library/Requests',
			'Symfony\Component\Finder' => WP_CLI_VENDOR_DIR . '/symfony/finder/',
		];

		foreach ( $mappings as $namespace => $folder ) {
			$autoloader->add_namespace(
				$namespace,
				$folder
			);
		}

		include_once WP_CLI_VENDOR_DIR . '/rmccue/requests/library/Requests.php';
		include_once WP_CLI_VENDOR_DIR . '/wp-cli/mustangostang-spyc/Spyc.php';

		$autoloader->register();

		return $state;
	}
}
