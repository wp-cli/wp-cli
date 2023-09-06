<?php

namespace WP_CLI\Bootstrap;

use WP_CLI\Autoloader;
use WP_CLI\RequestsLibrary;
use WP_CLI\Utils;

/**
 * Class IncludeRequestsAutoloader.
 *
 * Loads the Requests autoloader that best fits the current environment.
 *
 * If a WordPress installation is found, it autoloads that version of Requests.
 * Otherwise, it loads the version of Requests bundled with WP-CLI.
 *
 * This is done in order to avoid conflicts between Requests versions.
 *
 * @package WP_CLI\Bootstrap
 */
final class IncludeRequestsAutoloader implements BootstrapStep {

	/**
	 * Requests is being used from the WordPress installation.
	 *
	 * @var string
	 */
	const FROM_WP_CORE = 'wp-core';

	/**
	 * Requests is being used from the WP-CLI dependencies.
	 *
	 * @var string
	 */
	const FROM_WP_CLI = 'wp-cli';

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		// If Requests is already loaded, don't do anything.
		if ( class_exists( RequestsLibrary::CLASS_NAME_V2, false ) || class_exists( RequestsLibrary::CLASS_NAME_V1, false ) ) {
			return $state;
		}

		$runner = new RunnerInstance();

		// Use `--path` from the alias if one is matching.
		$alias_path = null;
		if ( $runner()->alias
			&& isset( $runner()->aliases[ $runner()->alias ]['path'] ) ) {
			$alias_path = $runner()->aliases[ $runner()->alias ]['path'];
			// Make sure it isn't an invalid value.
			if ( is_bool( $alias_path ) || empty( $alias_path ) ) {
				return $state;
			}
			if ( ! Utils\is_path_absolute( $alias_path ) ) {
				$alias_path = getcwd() . '/' . $alias_path;
			}
			$wp_root = rtrim( $alias_path, '/' );
		} else {
			// Make sure we don't deal with an invalid `--path` value.
			$config = $runner()->config;
			if ( isset( $config['path'] ) &&
				( is_bool( $config['path'] ) || empty( $config['path'] ) )
			) {
				return $state;
			}
			$wp_root = rtrim( $runner()->find_wp_root(), '/' );
		}

		// First try to detect a newer Requests version bundled with WordPress.
		if ( file_exists( $wp_root . '/wp-includes/Requests/src/Autoload.php' ) ) {
			if ( ! class_exists( '\\WpOrg\\Requests\\Autoload', false ) ) {
				require_once $wp_root . '/wp-includes/Requests/src/Autoload.php';
			}

			if ( class_exists( '\\WpOrg\\Requests\\Autoload' ) ) {
				\WpOrg\Requests\Autoload::register();
				$this->store_requests_meta( RequestsLibrary::CLASS_NAME_V2, self::FROM_WP_CORE );
				return $state;
			}
		}

		// Then see if we can detect the older version bundled with WordPress.
		if ( file_exists( $wp_root . '/wp-includes/class-requests.php' ) ) {
			if ( ! class_exists( '\\Requests', false ) ) {
				require_once $wp_root . '/wp-includes/class-requests.php';
			}

			if ( class_exists( '\\Requests' ) ) {
				\Requests::register_autoloader();
				$this->store_requests_meta( RequestsLibrary::CLASS_NAME_V1, self::FROM_WP_CORE );
				return $state;
			}
		}

		// Finally, fall back to the Requests version bundled with WP-CLI.
		$autoloader = new Autoloader();
		$autoloader->add_namespace(
			'WpOrg\Requests',
			WP_CLI_ROOT . '/bundle/rmccue/requests/src'
		);

		$autoloader->register();

		\WpOrg\Requests\Autoload::register();

		$this->store_requests_meta( RequestsLibrary::CLASS_NAME_V2, self::FROM_WP_CLI );

		return $state;
	}

	/**
	 * Store meta information about the used Requests integration.
	 *
	 * This can be used for all the conditional code that needs to work
	 * across multiple Requests versions.
	 *
	 * @param string $class_name The class name of the Requests integration.
	 * @param string $source     The source of the Requests integration.
	 */
	private function store_requests_meta( $class_name, $source ) {
		RequestsLibrary::set_version(
			RequestsLibrary::CLASS_NAME_V2 === $class_name
				? RequestsLibrary::VERSION_V2
				: RequestsLibrary::VERSION_V1
		);
		RequestsLibrary::set_source( $source );
		RequestsLibrary::set_class_name( $class_name );
	}
}
