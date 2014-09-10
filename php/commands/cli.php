<?php

use \WP_CLI\Dispatcher,
	\WP_CLI\Utils;

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
	function version() {
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
	function info( $_, $assoc_args ) {
		$php_bin = defined( 'PHP_BINARY' ) ? PHP_BINARY : getenv( 'WP_CLI_PHP_USED' );

		$runner = WP_CLI::get_runner();

		if ( isset( $assoc_args['format'] ) && 'json' === $assoc_args['format'] ) {
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
	 * Compare the last processed release to the current one, return true if it's the same minor version.
	 *
	 */
	private function same_minor_release( $release_parts, $updates ) {
		$previous = end( $updates );
		if ( false === $previous )
			return false;

		$previous_parts = explode( '.', $previous['version'] );

		return ( $previous_parts[0] === $release_parts[0]
			&& $previous_parts[1] === $release_parts[1] );
	}

	/**
	 * Check for update via Github API. Returns the available versions if there are updates, or empty if no update available.
	 *
	 * ## OPTIONS
	 *
	 * [--patch]
	 * : Compare only the first two parts of the version number.
	 *
	 * [--minor]
	 * : Compare only the first part of the version number.
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
	function check_update( $_, $assoc_args ) {
		$url = 'https://api.github.com/repos/wp-cli/wp-cli/releases';

		$options = array(
			'timeout' => 30
		);

		$headers = array(
			'Accept' => 'application/json'
		);
		$response = Utils\http_request( 'GET', $url, $headers, $options );

		if ( ! $response->success || 200 !== $response->status_code ) {
			WP_CLI::error( "Failed to get latest version." );
		}

		$release_data = json_decode( $response->body );
		$current_parts = explode( '.', WP_CLI_VERSION );
		$updates = array();

		foreach ( $release_data as $release ) {
			$release_version = $release->tag_name;
			// get rid of leading "v"
			if ( 'v' === substr( $release_version, 0, 1 ) ) {
				$release_version = ltrim( $release_version, 'v' );
			}
			// don't list earlier releases
			if ( version_compare( $release_version, WP_CLI_VERSION, '<=' ) )
				continue;
			$release_parts = explode( '.', $release_version );
			$update_type = 'minor';

			if ( $release_parts[0] === $current_parts[0]
				&& $release_parts[1] === $current_parts[1] ) {
				$update_type = 'patch';
			}

			if ( ! ( isset( $assoc_args['patch'] ) && 'patch' !== $update_type )
				&& ! ( isset( $assoc_args['minor'] ) && 'minor' !== $update_type )
				&& ! $this->same_minor_release( $release_parts, $updates )
				) {
				$updates[] = array(
					'version' => $release_version,
					'update_type' => $update_type,
					'package_url' => $release->assets[0]->browser_download_url
				);
			}
		}

		if ( $updates ) {
			$formatter = new \WP_CLI\Formatter(
				$assoc_args,
				array( 'version', 'update_type', 'package_url' )
			);
			$formatter->display_items( $updates );
		} else if ( empty( $assoc_args['format'] ) || 'table' == $assoc_args['format'] ) {
			WP_CLI::success( "WP-CLI is at the latest version." );
		}
	}

	/**
	 * Dump the list of global parameters, as JSON.
	 *
	 * @subcommand param-dump
	 */
	function param_dump() {
		echo json_encode( \WP_CLI::get_configurator()->get_spec() );
	}

	/**
	 * Dump the list of installed commands, as JSON.
	 *
	 * @subcommand cmd-dump
	 */
	function cmd_dump() {
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
	function completions( $_, $assoc_args ) {
		$line = substr( $assoc_args['line'], 0, $assoc_args['point'] );
		$compl = new \WP_CLI\Completions( $line );
		$compl->render();
	}
}

WP_CLI::add_command( 'cli', 'CLI_Command' );

