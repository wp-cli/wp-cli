<?php

use \Composer\Semver\Comparator;
use \WP_CLI\Dispatcher;
use \WP_CLI\Utils;

/**
 * Get information about WP-CLI itself.
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
	 */
	public function version() {
		WP_CLI::line( 'WP-CLI ' . WP_CLI_VERSION );
	}

	/**
	 * Print various data about the CLI environment.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Accepted values: json
	 */
	public function info( $_, $assoc_args ) {
		$php_bin = WP_CLI::get_php_binary();

		$runner = WP_CLI::get_runner();

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' ) === 'json' ) {
			$info = array(
				'php_binary_path' => $php_bin,
				'global_config_path' => $runner->global_config_path,
				'project_config_path' => $runner->project_config_path,
				'wp_cli_dir_path' => WP_CLI_ROOT,
				'wp_cli_version' => WP_CLI_VERSION,
			);

			WP_CLI::line( json_encode( $info ) );
		} else {
			WP_CLI::line( "PHP binary:\t" . $php_bin );
			WP_CLI::line( "PHP version:\t" . PHP_VERSION );
			WP_CLI::line( "php.ini used:\t" . get_cfg_var( 'cfg_file_path' ) );
			WP_CLI::line( "WP-CLI root dir:\t" . WP_CLI_ROOT );
			WP_CLI::line( "WP-CLI global config:\t" . $runner->global_config_path );
			WP_CLI::line( "WP-CLI project config:\t" . $runner->project_config_path );
			WP_CLI::line( "WP-CLI version:\t" . WP_CLI_VERSION );
		}
	}

	/**
	 * Check for update via Github API. Returns the available versions if there are updates, or empty if no update available.
	 *
	 * ## OPTIONS
	 *
	 * [--patch]
	 * : Only list patch updates
	 *
	 * [--minor]
	 * : Only list minor updates
	 *
	 * [--major]
	 * : Only list major updates
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each update.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields. Defaults to version,update_type,package_url.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count. Default: table
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
	 * Fetch most recent update matching the requirements. Returns the available versions if there are updates, or empty if no update available.
	 *
	 * ## OPTIONS
	 *
	 * [--patch]
	 * : Only perform patch updates
	 *
	 * [--minor]
	 * : Only perform minor updates
	 *
	 * [--major]
	 * : Only perform major updates
	 *
	 * [--nightly]
	 * : Update to the latest built version of the master branch. Potentially unstable.
	 *
	 * [--yes]
	 * : Do not prompt for confirmation
	 */
	public function update( $_, $assoc_args ) {
		if ( ! Utils\inside_phar() ) {
			WP_CLI::error( "You can only self-update Phar files." );
		}

		$old_phar = realpath( $_SERVER['argv'][0] );

		if ( ! is_writable( $old_phar ) ) {
			WP_CLI::error( sprintf( "%s is not writable by current user", $old_phar ) );
		} else if ( ! is_writeable( dirname( $old_phar ) ) ) {
			WP_CLI::error( sprintf( "%s is not writable by current user", dirname( $old_phar ) ) );
		}

		if ( isset( $assoc_args['nightly'] ) ) {

			WP_CLI::confirm( sprintf( 'You have version %s. Would you like to update to the latest nightly?', WP_CLI_VERSION ), $assoc_args );

			$download_url = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.phar';

		} else {

			$updates = $this->get_updates( $assoc_args );

			if ( empty( $updates ) ) {
				$update_type = $this->get_update_type_str( $assoc_args );
				WP_CLI::success( "WP-CLI is at the latest{$update_type}version." );
				exit(0);
			}

			$newest = $updates[0];

			WP_CLI::confirm( sprintf( 'You have version %s. Would you like to update to %s?', WP_CLI_VERSION, $newest['version'] ), $assoc_args );

			$download_url = $newest['package_url'];

		}

		WP_CLI::log( sprintf( 'Downloading from %s...', $download_url ) );

		$temp = sys_get_temp_dir() . '/' . uniqid('wp_') . '.phar';

		$headers = array();
		$options = array(
			'timeout' => 600,  // 10 minutes ought to be enough for everybody
			'filename' => $temp
		);

		Utils\http_request( 'GET', $download_url, null, $headers, $options );

		$allow_root = WP_CLI::get_runner()->config['allow-root'] ? '--allow-root' : '';
		$php_binary = WP_CLI::get_php_binary();
		$process = WP_CLI\Process::create( "{$php_binary} $temp --version {$allow_root}" );
		$result = $process->run();
		if ( 0 !== $result->return_code ) {
			$multi_line = explode( PHP_EOL, $result->stderr );
			WP_CLI::error_multi_line( $multi_line );
			WP_CLI::error( 'The downloaded PHAR is broken, try running wp cli update again.' );
		}

		WP_CLI::log( 'New version works. Proceeding to replace.' );

		$mode = fileperms( $old_phar ) & 511;

		if ( false === @chmod( $temp, $mode ) ) {
			WP_CLI::error( sprintf( "Cannot chmod %s", $temp ) );
		}

		class_exists( '\cli\Colors' ); // this autoloads \cli\Colors - after we move the file we no longer have access to this class

		if ( false === @rename( $temp, $old_phar ) ) {
			WP_CLI::error( sprintf( "Cannot move %s to %s", $temp, $old_phar ) );
		}

		if ( isset( $assoc_args['nightly'] ) ) {
			$updated_version = 'the latest nightly release';
		} else {
			$updated_version = $newest['version'];
		}
		WP_CLI::success( sprintf( 'Updated WP-CLI to %s', $updated_version ) );
	}

	/**
	 * Returns update information
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
			WP_CLI::error( sprintf( "Failed to get latest version (HTTP code %d)", $response->status_code ) );
		}

		$release_data = json_decode( $response->body );

		$updates = array(
			'major'      => false,
			'minor'      => false,
			'patch'      => false,
			);
		foreach ( $release_data as $release ) {

			// get rid of leading "v" if there is one set
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
	 * : Accepted values: var_export, json. Default: json.
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
	 * : The current command line to be executed
	 *
	 * --point=<point>
	 * : The index to the current cursor position relative to the beginning of the command
	 */
	public function completions( $_, $assoc_args ) {
		$line = substr( $assoc_args['line'], 0, $assoc_args['point'] );
		$compl = new \WP_CLI\Completions( $line );
		$compl->render();
	}

	/**
	 * Get a string representing the type of update being checked for
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

