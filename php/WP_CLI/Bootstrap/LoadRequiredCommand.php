<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;
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
	 * @return void
	 */
	public function process() {
		$runner = new RunnerInstance();
		if ( ! isset( $runner()->config['require'] ) ) {
			return;
		}

		foreach ( $runner()->config['require'] as $path ) {
			if ( ! file_exists( $path ) ) {
				$context = '';
				foreach ( array( 'global', 'project', 'runtime' ) as $scope ) {
					if ( in_array( $path, $runner()->_required_files[ $scope ], true ) ) {
						switch ( $scope ) {
							case 'global':
								$context = ' (from global ' . Utils\basename( $runner()->global_config_path ) . ')';
								break;
							case 'project':
								$context = ' (from project\'s ' . Utils\basename( $runner()->project_config_path ) . ')';
								break;
							case 'runtime':
								$context = ' (from runtime argument)';
								break;
						}
						break;
					}
				}
				WP_CLI::error( sprintf( "Required file '%s' doesn't exist%s.", Utils\basename( $path ), $context ) );
			}
			Utils\load_file( $path );
			WP_CLI::debug( 'Required file from config: ' . $path, 'bootstrap' );
		}
	}
}
