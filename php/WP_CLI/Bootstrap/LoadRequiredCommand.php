<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;
use WP_CLI\Configurator;
use WP_CLI\Path;
use WP_CLI\Utils;

/**
 * Class LoadRequiredCommand.
 *
 * Loads a command that was passed through the `--require=<command>` option.
 *
 * @package WP_CLI\Bootstrap
 */
final class LoadRequiredCommand implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		$runner = new RunnerInstance();
		if ( ! isset( $runner()->config['require'] ) || empty( $runner()->config['require'] ) ) {
			return $state;
		}

		$files_to_load = $runner()->config['require'];

		if ( $state->getValue( BootstrapState::IS_PROTECTED_COMMAND, false ) ) {
			// Protected commands must keep working even if a file required through
			// a config file or `--require` is broken, so those are skipped. Files
			// passed through the `WP_CLI_REQUIRE` environment variable are set by
			// whoever invokes WP-CLI itself (e.g. a test runner collecting code
			// coverage), so they are still loaded.
			$files_to_load = array_intersect( $files_to_load, Configurator::get_env_require_files() );
		}

		foreach ( $files_to_load as $path ) {
			if ( ! file_exists( $path ) ) {
				$context        = '';
				$required_files = $runner()->get_required_files();
				foreach ( [ 'system', 'global', 'project', 'runtime' ] as $scope ) {
					if ( isset( $required_files[ $scope ] ) && in_array( $path, $required_files[ $scope ], true ) ) {
						switch ( $scope ) {
							case 'system':
								$context = ' (from system ' . Path::basename( (string) $runner()->get_system_config_path() ) . ')';
								break;
							case 'global':
								$context = ' (from global ' . Path::basename( (string) $runner()->get_global_config_path() ) . ')';
								break;
							case 'project':
								$context = ' (from project\'s ' . Path::basename( (string) $runner()->get_project_config_path() ) . ')';
								break;
							case 'runtime':
								$context = ' (from runtime argument)';
								break;
						}
						break;
					}
				}
				WP_CLI::error( sprintf( "Required file '%s' doesn't exist%s.", Path::basename( $path ), $context ) );
			}
			Utils\load_file( $path );
			WP_CLI::debug( 'Required file from config: ' . $path, 'bootstrap' );
		}

		return $state;
	}
}
