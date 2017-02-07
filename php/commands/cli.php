<?php

use \Composer\Semver\Comparator;
use \WP_CLI\Dispatcher;
use \WP_CLI\Utils;

/**
 * Manage WP-CLI itself.
 *
 * ## EXAMPLES
 *
 *     # Display the version currently installed.
 *     $ wp cli version
 *     WP-CLI 0.24.1
 *
 *     # Check for updates to WP-CLI.
 *     $ wp cli check-update
 *     Success: WP-CLI is at the latest version.
 *
 *     # Update WP-CLI to the latest stable release.
 *     $ wp cli update
 *     You have version 0.24.0. Would you like to update to 0.24.1? [y/n] y
 *     Downloading from https://github.com/wp-cli/wp-cli/releases/download/v0.24.1/wp-cli-0.24.1.phar...
 *     New version works. Proceeding to replace.
 *     Success: Updated WP-CLI to 0.24.1.
 *
 * @when before_wp_load
 */
class CLI_Command extends WP_CLI_Command {

	private function command_to_array( $command ) {
		$dump = array(
			'name' => $command->get_name(),
			'description' => $command->get_shortdesc(),
			'longdesc' => $command->get_longdesc(),
		);

		foreach ( $command->get_subcommands() as $subcommand ) {
			$dump['subcommands'][] = self::command_to_array( $subcommand );
		}

		if ( empty( $dump['subcommands'] ) ) {
			$dump['synopsis'] = (string) $command->get_synopsis();
		}

		return $dump;
	}

	/**
	 * Print WP-CLI version.
	 *
	 * ## EXAMPLES
	 *
	 *     # Display CLI version.
	 *     $ wp cli version
	 *     WP-CLI 0.24.1
	 */
	public function version() {
		WP_CLI::line( 'WP-CLI ' . WP_CLI_VERSION );
	}

	/**
	 * Print various details about the WP-CLI environment.
	 *
	 * Helpful for diagnostic purposes, this command shares:
	 *
	 * * PHP binary used.
	 * * PHP binary version.
	 * * php.ini configuration file used (which is typically different than web).
	 * * WP-CLI root dir: where WP-CLI is installed (if non-Phar install).
	 * * WP-CLI global config: where the global config YAML file is located.
	 * * WP-CLI project config: where the project config YAML file is located.
	 * * WP-CLI version: currently installed version.
	 *
	 * See [config docs](https://wp-cli.org/config/) for more details on global
	 * and project config YAML files.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: list
	 * options:
	 *   - list
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Display various data about the CLI environment.
	 *     $ wp cli info
	 *     PHP binary: /usr/bin/php5
	 *     PHP version:    5.5.9-1ubuntu4.16
	 *     php.ini used:   /etc/php5/cli/php.ini
	 *     WP-CLI root dir:    phar://wp-cli.phar
	 *     WP-CLI packages dir:    /home/person/.wp-cli/packages/
	 *     WP-CLI global config:
	 *     WP-CLI project config:
	 *     WP-CLI version: 0.24.1
	 */
	public function info( $_, $assoc_args ) {
		$php_bin = WP_CLI::get_php_binary();

		$runner = WP_CLI::get_runner();

		$packages_dir = $runner->get_packages_dir_path();
		if ( ! is_dir( $packages_dir ) ) {
			$packages_dir = null;
		}
		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' ) === 'json' ) {
			$info = array(
				'php_binary_path' => $php_bin,
				'global_config_path' => $runner->global_config_path,
				'project_config_path' => $runner->project_config_path,
				'wp_cli_dir_path' => WP_CLI_ROOT,
				'wp_cli_packages_dir_path' => $packages_dir,
				'wp_cli_version' => WP_CLI_VERSION,
			);

			WP_CLI::line( json_encode( $info ) );
		} else {
			WP_CLI::line( "PHP binary:\t" . $php_bin );
			WP_CLI::line( "PHP version:\t" . PHP_VERSION );
			WP_CLI::line( "php.ini used:\t" . get_cfg_var( 'cfg_file_path' ) );
			WP_CLI::line( "WP-CLI root dir:\t" . WP_CLI_ROOT );
			WP_CLI::line( "WP-CLI packages dir:\t" . $packages_dir );
			WP_CLI::line( "WP-CLI global config:\t" . $runner->global_config_path );
			WP_CLI::line( "WP-CLI project config:\t" . $runner->project_config_path );
			WP_CLI::line( "WP-CLI version:\t" . WP_CLI_VERSION );
		}
	}

	/**
	 * Check to see if there is a newer version of WP-CLI available.
	 *
	 * Queries the Github releases API. Returns available versions if there are
	 * updates available, or success message if using the latest release.
	 *
	 * ## OPTIONS
	 *
	 * [--patch]
	 * : Only list patch updates.
	 *
	 * [--minor]
	 * : Only list minor updates.
	 *
	 * [--major]
	 * : Only list major updates.
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each update.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields. Defaults to version,update_type,package_url.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Check for update.
	 *     $ wp cli check-update
	 *     Success: WP-CLI is at the latest version.
	 *
	 *     # Check for update and new version is available.
	 *     $ wp cli check-update
	 *     +---------+-------------+-------------------------------------------------------------------------------+
	 *     | version | update_type | package_url                                                                   |
	 *     +---------+-------------+-------------------------------------------------------------------------------+
	 *     | 0.24.1  | patch       | https://github.com/wp-cli/wp-cli/releases/download/v0.24.1/wp-cli-0.24.1.phar |
	 *     +---------+-------------+-------------------------------------------------------------------------------+
	 *
	 * @subcommand check-update
	 */
	public function check_update( $_, $assoc_args ) {
		$updates = $this->get_updates( $assoc_args );

		if ( $updates ) {
			$formatter = new \WP_CLI\Formatter(
				$assoc_args,
				array( 'version', 'update_type', 'package_url' )
			);
			$formatter->display_items( $updates );
		} else if ( empty( $assoc_args['format'] ) || 'table' == $assoc_args['format'] ) {
			$update_type = $this->get_update_type_str( $assoc_args );
			WP_CLI::success( "WP-CLI is at the latest{$update_type}version." );
		}
	}

	/**
	 * Update WP-CLI to the latest release.
	 *
	 * Default behavior is to check the releases API for the newest stable
	 * version, and prompt if one is available.
	 *
	 * Use `--stable` to install or reinstall the latest stable version.
	 *
	 * Use `--nightly` to install the latest built version of the master branch.
	 * While not recommended for production, nightly contains the latest and
	 * greatest, and should be stable enough for development and staging
	 * environments.
	 *
	 * Only works for the Phar installation mechanism.
	 *
	 * ## OPTIONS
	 *
	 * [--patch]
	 * : Only perform patch updates.
	 *
	 * [--minor]
	 * : Only perform minor updates.
	 *
	 * [--major]
	 * : Only perform major updates.
	 *
	 * [--stable]
	 * : Update to the latest stable release. Skips update check.
	 *
	 * [--nightly]
	 * : Update to the latest built version of the master branch. Potentially unstable.
	 *
	 * [--yes]
	 * : Do not prompt for confirmation.
	 *
	 * ## EXAMPLES
	 *
	 *     # Update CLI.
	 *     $ wp cli update
	 *     You have version 0.24.0. Would you like to update to 0.24.1? [y/n] y
	 *     Downloading from https://github.com/wp-cli/wp-cli/releases/download/v0.24.1/wp-cli-0.24.1.phar...
	 *     New version works. Proceeding to replace.
	 *     Success: Updated WP-CLI to 0.24.1.
	 */
	public function update( $_, $assoc_args ) {
		if ( ! Utils\inside_phar() ) {
			WP_CLI::error( "You can only self-update Phar files." );
		}

		$old_phar = realpath( $_SERVER['argv'][0] );

		if ( ! is_writable( $old_phar ) ) {
			WP_CLI::error( sprintf( "%s is not writable by current user.", $old_phar ) );
		} else if ( ! is_writeable( dirname( $old_phar ) ) ) {
			WP_CLI::error( sprintf( "%s is not writable by current user.", dirname( $old_phar ) ) );
		}

		if ( Utils\get_flag_value( $assoc_args, 'nightly' ) ) {
			WP_CLI::confirm( sprintf( 'You have version %s. Would you like to update to the latest nightly?', WP_CLI_VERSION ), $assoc_args );
			$download_url = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.phar';
			$md5_url = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.phar.md5';
		} else if ( Utils\get_flag_value( $assoc_args, 'stable' ) ) {
			WP_CLI::confirm( sprintf( 'You have version %s. Would you like to update to the latest stable release?', WP_CLI_VERSION ), $assoc_args );
			$download_url = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar';
			$md5_url = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar.md5';
		} else {

			$updates = $this->get_updates( $assoc_args );

			if ( empty( $updates ) ) {
				$update_type = $this->get_update_type_str( $assoc_args );
				WP_CLI::success( "WP-CLI is at the latest{$update_type}version." );
				return;
			}

			$newest = $updates[0];

			WP_CLI::confirm( sprintf( 'You have version %s. Would you like to update to %s?', WP_CLI_VERSION, $newest['version'] ), $assoc_args );

			$download_url = $newest['package_url'];
			$md5_url = str_replace( '.phar', '.phar.md5', $download_url );
		}

		WP_CLI::log( sprintf( 'Downloading from %s...', $download_url ) );

		$temp = \WP_CLI\Utils\get_temp_dir() . uniqid('wp_') . '.phar';

		$headers = array();
		$options = array(
			'timeout' => 600,  // 10 minutes ought to be enough for everybody.
			'filename' => $temp
		);

		Utils\http_request( 'GET', $download_url, null, $headers, $options );

		$md5_response = Utils\http_request( 'GET', $md5_url );
		if ( 20 != substr( $md5_response->status_code, 0, 2 ) ) {
			WP_CLI::error( "Couldn't access md5 hash for release (HTTP code {$md5_response->status_code})." );
		}
		$md5_file = md5_file( $temp );
		$release_hash = trim( $md5_response->body );
		if ( $md5_file === $release_hash ) {
			WP_CLI::log( 'md5 hash verified: ' . $release_hash );
		} else {
			WP_CLI::error( "md5 hash for download ({$md5_file}) is different than the release hash ({$release_hash})." );
		}

		$allow_root = WP_CLI::get_runner()->config['allow-root'] ? '--allow-root' : '';
		$php_binary = WP_CLI::get_php_binary();
		$process = WP_CLI\Process::create( "{$php_binary} $temp --info {$allow_root}" );
		$result = $process->run();
		if ( 0 !== $result->return_code || false === stripos( $result->stdout, 'WP-CLI version:' ) ) {
			$multi_line = explode( PHP_EOL, $result->stderr );
			WP_CLI::error_multi_line( $multi_line );
			WP_CLI::error( 'The downloaded PHAR is broken, try running wp cli update again.' );
		}

		WP_CLI::log( 'New version works. Proceeding to replace.' );

		$mode = fileperms( $old_phar ) & 511;

		if ( false === @chmod( $temp, $mode ) ) {
			WP_CLI::error( sprintf( "Cannot chmod %s.", $temp ) );
		}

		class_exists( '\cli\Colors' ); // This autoloads \cli\Colors - after we move the file we no longer have access to this class.

		if ( false === @rename( $temp, $old_phar ) ) {
			WP_CLI::error( sprintf( "Cannot move %s to %s", $temp, $old_phar ) );
		}

		if ( Utils\get_flag_value( $assoc_args, 'nightly' ) ) {
			$updated_version = 'the latest nightly release';
		} else if ( Utils\get_flag_value( $assoc_args, 'stable' ) ) {
			$updated_version = 'the latest stable release';
		} else {
			$updated_version = $newest['version'];
		}
		WP_CLI::success( sprintf( 'Updated WP-CLI to %s.', $updated_version ) );
	}

	/**
	 * Returns update information.
	 */
	private function get_updates( $assoc_args ) {
		$url = 'https://api.github.com/repos/wp-cli/wp-cli/releases';

		$options = array(
			'timeout' => 30
		);

		$headers = array(
			'Accept' => 'application/json'
		);
		$response = Utils\http_request( 'GET', $url, $headers, $options );

		if ( ! $response->success || 200 !== $response->status_code ) {
			WP_CLI::error( sprintf( "Failed to get latest version (HTTP code %d).", $response->status_code ) );
		}

		$release_data = json_decode( $response->body );

		$updates = array(
			'major'      => false,
			'minor'      => false,
			'patch'      => false,
			);
		foreach ( $release_data as $release ) {

			// Get rid of leading "v" if there is one set.
			$release_version = $release->tag_name;
			if ( 'v' === substr( $release_version, 0, 1 ) ) {
				$release_version = ltrim( $release_version, 'v' );
			}

			$update_type = Utils\get_named_sem_ver( $release_version, WP_CLI_VERSION );
			if ( ! $update_type ) {
				continue;
			}

			if ( ! empty( $updates[ $update_type ] ) && ! Comparator::greaterThan( $release_version, $updates[ $update_type ]['version'] ) ) {
				continue;
			}

			$updates[ $update_type ] = array(
				'version' => $release_version,
				'update_type' => $update_type,
				'package_url' => $release->assets[0]->browser_download_url
			);
		}

		foreach( $updates as $type => $value ) {
			if ( empty( $value ) ) {
				unset( $updates[ $type ] );
			}
		}

		foreach( array( 'major', 'minor', 'patch' ) as $type ) {
			if ( true === \WP_CLI\Utils\get_flag_value( $assoc_args, $type ) ) {
				return ! empty( $updates[ $type ] ) ? array( $updates[ $type ] ) : false;
			}
		}

		if ( empty( $updates ) && preg_match( '#-alpha-(.+)$#', WP_CLI_VERSION, $matches ) ) {
			$version_url = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/NIGHTLY_VERSION';
			$response = Utils\http_request( 'GET', $version_url );
			if ( ! $response->success || 200 !== $response->status_code ) {
				WP_CLI::error( sprintf( "Failed to get current nightly version (HTTP code %d)", $response->status_code ) );
			}
			$nightly_version = trim( $response->body );
			if ( WP_CLI_VERSION != $nightly_version ) {
				$updates['nightly'] = array(
					'version'        => $nightly_version,
					'update_type'    => 'nightly',
					'package_url'    => 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.phar',
				);
			}
		}

		return array_values( $updates );
	}

	/**
	 * Dump the list of global parameters, as JSON or in var_export format.
	 *
	 * ## OPTIONS
	 *
	 * [--with-values]
	 * : Display current values also.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: json
	 * options:
	 *   - var_export
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Dump the list of global parameters.
	 *     $ wp cli param-dump --format=var_export
	 *     array (
	 *       'path' =>
	 *       array (
	 *         'runtime' => '=<path>',
	 *         'file' => '<path>',
	 *         'synopsis' => '',
	 *         'default' => NULL,
	 *         'multiple' => false,
	 *         'desc' => 'Path to the WordPress files.',
	 *       ),
	 *       'url' =>
	 *       array (
	 *
	 * @subcommand param-dump
	 */
	function param_dump( $_, $assoc_args ) {
		$spec = \WP_CLI::get_configurator()->get_spec();

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'with-values' ) ) {
			$config = \WP_CLI::get_configurator()->to_array();
			// Copy current config values to $spec
			foreach ( $spec as $key => $value ) {
				if ( isset( $config[0][$key] ) ) {
					$current = $config[0][$key];
				} else {
					$current = NULL;
				}
				$spec[$key]['current'] = $current;
			}
		}

		if ( 'var_export' === \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' ) ) {
			var_export( $spec );
		} else {
			echo json_encode( $spec );
		}
	}

	/**
	 * Dump the list of installed commands, as JSON.
	 *
	 * ## EXAMPLES
	 *
	 *     # Dump the list of installed commands.
	 *     $ wp cli cmd-dump
	 *     {"name":"wp","description":"Manage WordPress through the command-line.","longdesc":"\n\n## GLOBAL PARAMETERS\n\n  --path=<path>\n      Path to the WordPress files.\n\n  --ssh=<ssh>\n      Perform operation against a remote server over SSH.\n\n  --url=<url>\n      Pretend request came from given URL. In multisite, this argument is how the target site is specified. \n\n  --user=<id|login|email>\n
	 *
	 * @subcommand cmd-dump
	 */
	public function cmd_dump() {
		echo json_encode( self::command_to_array( WP_CLI::get_root_command() ) );
	}

	/**
	 * Generate tab completion strings.
	 *
	 * ## OPTIONS
	 *
	 * --line=<line>
	 * : The current command line to be executed.
	 *
	 * --point=<point>
	 * : The index to the current cursor position relative to the beginning of the command.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate tab completion strings.
	 *     $ wp cli completions --line='wp eva' --point=100
	 *     eval
	 *     eval-file
	 */
	public function completions( $_, $assoc_args ) {
		$line = substr( $assoc_args['line'], 0, $assoc_args['point'] );
		$compl = new \WP_CLI\Completions( $line );
		$compl->render();
	}

	/**
	 * List available WP-CLI aliases.
	 *
	 * Aliases are shorthand references to WordPress installs. For instance,
	 * `@dev` could refer to a development install and `@prod` could refer to
	 * a production install. This command gives you visibility in what
	 * registered aliases you have available.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: yaml
	 * options:
	 *   - yaml
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List all available aliases.
	 *     $ wp cli alias
	 *     ---
	 *     @all: Run command against every registered alias.
	 *     @prod:
	 *       ssh: runcommand@runcommand.io~/webapps/production
	 *     @dev:
	 *       ssh: vagrant@192.168.50.10/srv/www/runcommand.dev
	 *     @both:
	 *       - @prod
	 *       - @dev
	 *
	 * @alias aliases
	 */
	public function alias( $_, $assoc_args ) {
		WP_CLI::print_value( WP_CLI::get_runner()->aliases, $assoc_args );
	}

	/**
	 * Get a string representing the type of update being checked for.
	 */
	private function get_update_type_str( $assoc_args ) {
		$update_type = ' ';
		foreach( array( 'major', 'minor', 'patch' ) as $type ) {
			if ( true === \WP_CLI\Utils\get_flag_value( $assoc_args, $type ) ) {
				$update_type = ' ' . $type . ' ';
				break;
			}
		}
		return $update_type;
	}

}

WP_CLI::add_command( 'cli', 'CLI_Command' );
