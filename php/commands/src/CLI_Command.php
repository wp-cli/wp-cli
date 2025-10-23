<?php

use Composer\Semver\Comparator;
use WP_CLI\Completions;
use WP_CLI\Formatter;
use WP_CLI\Process;
use WP_CLI\Utils;

/**
 * Reviews current WP-CLI info, checks for updates, or views defined aliases.
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
 *     # Clear the internal WP-CLI cache.
 *     $ wp cli cache clear
 *     Success: Cache cleared.
 *
 * @when before_wp_load
 *
 * @phpstan-type GitHubRelease object{tag_name: string, assets: array<object{browser_download_url: string}>}
 *
 * @phpstan-type UpdateOffer array{version: string, update_type: string, package_url: string, status: string, requires_php: string}
 */
class CLI_Command extends WP_CLI_Command {

	private function command_to_array( $command ) {
		$dump = [
			'name'        => $command->get_name(),
			'description' => $command->get_shortdesc(),
			'longdesc'    => $command->get_longdesc(),
			'hook'        => $command->get_hook(),
		];

		foreach ( $command->get_subcommands() as $subcommand ) {
			$dump['subcommands'][] = $this->command_to_array( $subcommand );
		}

		if ( empty( $dump['subcommands'] ) ) {
			$dump['synopsis'] = (string) $command->get_synopsis();
		}

		return $dump;
	}

	/**
	 * Prints WP-CLI version.
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
	 * Prints various details about the WP-CLI environment.
	 *
	 * Helpful for diagnostic purposes, this command shares:
	 *
	 * * OS information.
	 * * Shell information.
	 * * PHP binary used.
	 * * PHP binary version.
	 * * php.ini configuration file used (which is typically different than web).
	 * * WP-CLI root dir: where WP-CLI is installed (if non-Phar install).
	 * * WP-CLI global config: where the global config YAML file is located.
	 * * WP-CLI project config: where the project config YAML file is located.
	 * * WP-CLI version: currently installed version.
	 *
	 * See [config docs](https://make.wordpress.org/cli/handbook/references/config/) for more details on global
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
	 *     OS:  Linux 4.10.0-42-generic #46~16.04.1-Ubuntu SMP Mon Dec 4 15:57:59 UTC 2017 x86_64
	 *     Shell:   /usr/bin/zsh
	 *     PHP binary:  /usr/bin/php
	 *     PHP version: 7.1.12-1+ubuntu16.04.1+deb.sury.org+1
	 *     php.ini used:    /etc/php/7.1/cli/php.ini
	 *     WP-CLI root dir:    phar://wp-cli.phar
	 *     WP-CLI packages dir:    /home/person/.wp-cli/packages/
	 *     WP-CLI global config:
	 *     WP-CLI project config:
	 *     WP-CLI version: 1.5.0
	 *
	 * @param array $args                       Positional arguments. Unused.
	 * @param array $assoc_args{format: string} Associative arguments.
	 */
	public function info( $args, $assoc_args ) {
		$system_os = sprintf(
			'%s %s %s %s',
			php_uname( 's' ),
			php_uname( 'r' ),
			php_uname( 'v' ),
			php_uname( 'm' )
		);

		$shell = getenv( 'SHELL' );
		if ( ! $shell && Utils\is_windows() ) {
			$shell = getenv( 'ComSpec' );
		}

		$php_bin = Utils\get_php_binary();

		$runner = WP_CLI::get_runner();

		$packages_dir = $runner->get_packages_dir_path();
		if ( ! is_dir( $packages_dir ) ) {
			$packages_dir = null;
		}

		if ( Utils\get_flag_value( $assoc_args, 'format' ) === 'json' ) {
			$info = [
				'system_os'                => $system_os,
				'shell'                    => $shell,
				'php_binary_path'          => $php_bin,
				'php_version'              => PHP_VERSION,
				'php_ini_used'             => get_cfg_var( 'cfg_file_path' ),
				'mysql_binary_path'        => Utils\get_mysql_binary_path(),
				'mysql_version'            => Utils\get_mysql_version(),
				'sql_modes'                => Utils\get_sql_modes(),
				'wp_cli_dir_path'          => WP_CLI_ROOT,
				'wp_cli_vendor_path'       => WP_CLI_VENDOR_DIR,
				'wp_cli_phar_path'         => defined( 'WP_CLI_PHAR_PATH' ) ? WP_CLI_PHAR_PATH : '',
				'wp_cli_packages_dir_path' => $packages_dir,
				'wp_cli_cache_dir_path'    => Utils\get_cache_dir(),
				'global_config_path'       => $runner->global_config_path,
				'project_config_path'      => $runner->project_config_path,
				'wp_cli_version'           => WP_CLI_VERSION,
			];

			WP_CLI::line( (string) json_encode( $info ) );
		} else {
			/**
			 * @var string $cfg_file_path
			 */
			$cfg_file_path = get_cfg_var( 'cfg_file_path' );
			WP_CLI::line( "OS:\t" . $system_os );
			WP_CLI::line( "Shell:\t" . $shell );
			WP_CLI::line( "PHP binary:\t" . $php_bin );
			WP_CLI::line( "PHP version:\t" . PHP_VERSION );
			WP_CLI::line( "php.ini used:\t" . $cfg_file_path );
			WP_CLI::line( "MySQL binary:\t" . Utils\get_mysql_binary_path() );
			WP_CLI::line( "MySQL version:\t" . Utils\get_mysql_version() );
			WP_CLI::line( "SQL modes:\t" . implode( ',', Utils\get_sql_modes() ) );
			WP_CLI::line( "WP-CLI root dir:\t" . WP_CLI_ROOT );
			WP_CLI::line( "WP-CLI vendor dir:\t" . WP_CLI_VENDOR_DIR );
			WP_CLI::line( "WP_CLI phar path:\t" . ( defined( 'WP_CLI_PHAR_PATH' ) ? WP_CLI_PHAR_PATH : '' ) );
			WP_CLI::line( "WP-CLI packages dir:\t" . $packages_dir );
			WP_CLI::line( "WP-CLI cache dir:\t" . Utils\get_cache_dir() );
			WP_CLI::line( "WP-CLI global config:\t" . $runner->global_config_path );
			WP_CLI::line( "WP-CLI project config:\t" . $runner->project_config_path );
			WP_CLI::line( "WP-CLI version:\t" . WP_CLI_VERSION );
		}
	}

	/**
	 * Checks to see if there is a newer version of WP-CLI available.
	 *
	 * Queries the GitHub releases API. Returns available versions if there are
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
	 * : Limit the output to specific object fields. Defaults to version,update_type,package_url,status,requires_php.
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
	 *
	 * @param array $args Positional arguments. Unused.
	 * @param array $assoc_args{patch?: bool, minor?: bool, major?: bool, field?: string, fields?: string, format: string} Associative arguments.
	 */
	public function check_update( $args, $assoc_args ) {
		$updates = $this->get_updates( $assoc_args );

		if ( $updates ) {
			$formatter = new Formatter(
				$assoc_args,
				[ 'version', 'update_type', 'package_url', 'status', 'requires_php' ]
			);
			$formatter->display_items( $updates );
		} elseif ( empty( $assoc_args['format'] ) || 'table' === $assoc_args['format'] ) {
			$update_type = $this->get_update_type_str( $assoc_args );
			WP_CLI::success( "WP-CLI is at the latest{$update_type}version." );
		}
	}

	/**
	 * Updates WP-CLI to the latest release.
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
	 * [--insecure]
	 * : Retry without certificate validation if TLS handshake fails. Note: This makes the request vulnerable to a MITM attack.
	 *
	 * ## EXAMPLES
	 *
	 *     # Update CLI.
	 *     $ wp cli update
	 *     You are currently using WP-CLI version 0.24.0. Would you like to update to 0.24.1? [y/n] y
	 *     Downloading from https://github.com/wp-cli/wp-cli/releases/download/v0.24.1/wp-cli-0.24.1.phar...
	 *     New version works. Proceeding to replace.
	 *     Success: Updated WP-CLI to 0.24.1.
	 *
	 * @param array $args Positional arguments. Unused.
	 * @param array $assoc_args{patch?: bool, minor?: bool, major?: bool, stable?: bool, nightly?: bool, yes?: bool, insecure?: bool} Associative arguments.
	 */
	public function update( $args, $assoc_args ) {
		if ( ! Utils\inside_phar() ) {
			WP_CLI::error( 'You can only self-update Phar files.' );
		}

		$old_phar = (string) realpath( $_SERVER['argv'][0] );

		if ( ! is_writable( $old_phar ) ) {
			WP_CLI::error( sprintf( '%s is not writable by current user.', $old_phar ) );
		} elseif ( ! is_writable( dirname( $old_phar ) ) ) {
			WP_CLI::error( sprintf( '%s is not writable by current user.', dirname( $old_phar ) ) );
		}

		if ( Utils\get_flag_value( $assoc_args, 'nightly' ) ) {
			WP_CLI::confirm( sprintf( 'You are currently using WP-CLI version %s. Would you like to update to the latest nightly version?', WP_CLI_VERSION ), $assoc_args );
			$download_url = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.phar';
			$md5_url      = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.phar.md5';
			$sha512_url   = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.phar.sha512';
		} elseif ( Utils\get_flag_value( $assoc_args, 'stable' ) ) {
			WP_CLI::confirm( sprintf( 'You are currently using WP-CLI version %s. Would you like to update to the latest stable release?', WP_CLI_VERSION ), $assoc_args );
			$download_url = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar';
			$md5_url      = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar.md5';
			$sha512_url   = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar.sha512';
		} else {

			$updates = $this->get_updates( $assoc_args );

			/**
			 * @phpstan-var UpdateOffer|null $newest
			 */
			$newest = $this->array_find(
				$updates,
				static function ( $update ) {
					return 'available' === $update['status'];
				}
			);

			if ( ! $newest ) {
				$update_type = $this->get_update_type_str( $assoc_args );
				WP_CLI::success( "WP-CLI is at the latest{$update_type}version." );
				return;
			}

			WP_CLI::confirm( sprintf( 'You have version %s. Would you like to update to %s?', WP_CLI_VERSION, $newest['version'] ), $assoc_args );

			$download_url = $newest['package_url'];
			$md5_url      = str_replace( '.phar', '.phar.md5', $download_url );
			$sha512_url   = str_replace( '.phar', '.phar.sha512', $download_url );
		}

		WP_CLI::log( sprintf( 'Downloading from %s...', $download_url ) );

		$temp = Utils\get_temp_dir() . uniqid( 'wp_', true ) . '.phar';

		$headers = [];
		$options = [
			'timeout'  => 600,  // 10 minutes ought to be enough for everybody.
			'filename' => $temp,
			'insecure' => (bool) Utils\get_flag_value( $assoc_args, 'insecure', false ),
		];

		Utils\http_request( 'GET', $download_url, null, $headers, $options );

		unset( $options['filename'] );

		$this->validate_hashes( $temp, $sha512_url, $md5_url );

		$allow_root = WP_CLI::get_runner()->config['allow-root'] ? '--allow-root' : '';
		$php_binary = Utils\get_php_binary();
		$process    = Process::create( Utils\esc_cmd( '%s %s --info %s', $php_binary, $temp, $allow_root ) );
		$result     = $process->run();
		if ( 0 !== $result->return_code || false === stripos( $result->stdout, 'WP-CLI version' ) ) {
			$multi_line = explode( PHP_EOL, $result->stderr );
			WP_CLI::error_multi_line( $multi_line );
			WP_CLI::error( 'The downloaded PHAR is broken, try running wp cli update again.' );
		}

		WP_CLI::log( 'New version works. Proceeding to replace.' );

		$mode = fileperms( $old_phar ) & 511;

		if ( false === chmod( $temp, $mode ) ) {
			WP_CLI::error( sprintf( 'Cannot chmod %s.', $temp ) );
		}

		class_exists( '\cli\Colors' ); // This autoloads \cli\Colors - after we move the file we no longer have access to this class.

		if ( false === rename( $temp, $old_phar ) ) {
			WP_CLI::error( sprintf( 'Cannot move %s to %s', $temp, $old_phar ) );
		}

		if ( Utils\get_flag_value( $assoc_args, 'nightly', false ) ) {
			$updated_version = 'the latest nightly release';
		} elseif ( Utils\get_flag_value( $assoc_args, 'stable', false ) ) {
			$updated_version = 'the latest stable release';
		} else {
			$updated_version = isset( $newest['version'] ) ? $newest['version'] : '<not provided>';
		}
		WP_CLI::success( sprintf( 'Updated WP-CLI to %s.', $updated_version ) );
	}

	/**
	 * @param string $file       Release file path.
	 * @param string $sha512_url URL to sha512 hash.
	 * @param string $md5_url    URL to md5 hash.
	 *
	 * @throws \WP_CLI\ExitException
	 */
	private function validate_hashes( $file, $sha512_url, $md5_url ): void {
		$algos = [
			'sha512' => $sha512_url,
			'md5'    => $md5_url,
		];

		foreach ( $algos as $algo => $url ) {
			$response = Utils\http_request( 'GET', $url );
			if ( '20' !== substr( (string) $response->status_code, 0, 2 ) ) {
				WP_CLI::log( "Couldn't access $algo hash for release (HTTP code {$response->status_code})." );
				continue;
			}

			$file_hash = hash_file( $algo, $file );

			$release_hash = trim( $response->body );
			if ( $file_hash === $release_hash ) {
				WP_CLI::log( "$algo hash verified: $release_hash" );
				return;
			} else {
				WP_CLI::error( "$algo hash for download ($file_hash) is different than the release hash ($release_hash)." );
			}
		}

		WP_CLI::error( 'Release hash verification failed.' );
	}

	/**
	 * Returns update information.
	 */
	private function get_updates( $assoc_args ) {
		$url = 'https://api.github.com/repos/wp-cli/wp-cli/releases?per_page=100';

		$options = [
			'timeout'  => 30,
			'insecure' => (bool) Utils\get_flag_value( $assoc_args, 'insecure', false ),
		];

		$headers = [
			'Accept' => 'application/json',
		];

		$github_token = getenv( 'GITHUB_TOKEN' );
		if ( false !== $github_token ) {
			$headers['Authorization'] = 'token ' . $github_token;
		}

		$response = Utils\http_request( 'GET', $url, null, $headers, $options );

		if ( ! $response->success || 200 !== $response->status_code ) {
			WP_CLI::error( sprintf( 'Failed to get latest version (HTTP code %d).', $response->status_code ) );
		}

		/**
		 * @phpstan-var GitHubRelease[] $release_data
		 */
		$release_data = json_decode( $response->body, false );

		$updates = [
			'major' => false,
			'minor' => false,
			'patch' => false,
		];

		$updates_unavailable = [];

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

			// Release is older than one we already have on file.
			if ( ! empty( $updates[ $update_type ] ) && ! Comparator::greaterThan( $release_version, $updates[ $update_type ]['version'] ) ) {
				continue;
			}

			$package_url = null;

			/**
			 * WP-CLI manifest.json data.
			 *
			 * @var object{requires_php?: string}|null $manifest_data
			 */
			$manifest_data = null;

			foreach ( $release->assets as $asset ) {
				if ( ! isset( $asset->browser_download_url ) ) {
					continue;
				}

				if ( substr( $asset->browser_download_url, - strlen( '.phar' ) ) === '.phar' ) {
					$package_url = $asset->browser_download_url;
				}

				// The manifest.json file, if it exists, contains information about PHP version requirements and similar.
				if ( substr( $asset->browser_download_url, - strlen( 'manifest.json' ) ) === 'manifest.json' ) {
					$response = Utils\http_request( 'GET', $asset->browser_download_url, null, $headers, $options );

					if ( $response->success ) {
						/**
						 * WP-CLI manifest.json data.
						 *
						 * @var object{requires_php?: string}|null $manifest_data
						 */
						$manifest_data = json_decode( $response->body, false );
					}
				}
			}

			if ( ! $package_url ) {
				continue;
			}

			// Release requires a newer version of PHP.
			if (
				isset( $manifest_data->requires_php ) &&
				! Comparator::greaterThanOrEqualTo( PHP_VERSION, $manifest_data->requires_php )
			) {
				$updates_unavailable[] = [
					'version'      => $release_version,
					'update_type'  => $update_type,
					'package_url'  => $release->assets[0]->browser_download_url,
					'status'       => 'unavailable',
					'requires_php' => $manifest_data->requires_php,
				];
			} else {
				$updates[ $update_type ] = [
					'version'      => $release_version,
					'update_type'  => $update_type,
					'package_url'  => $release->assets[0]->browser_download_url,
					'status'       => 'available',
					'requires_php' => isset( $manifest_data->requires_php ) ? $manifest_data->requires_php : '',
				];
			}
		}

		foreach ( $updates as $type => $value ) {
			if ( empty( $value ) ) {
				unset( $updates[ $type ] );
			}
		}

		foreach ( [ 'major', 'minor', 'patch' ] as $type ) {
			if ( true === Utils\get_flag_value( $assoc_args, $type ) ) {
				return ! empty( $updates[ $type ] ) ? [ $updates[ $type ] ] : false;
			}
		}

		if ( empty( $updates ) && preg_match( '#-alpha-(.+)$#', WP_CLI_VERSION, $matches ) ) {
			$version_url = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/NIGHTLY_VERSION';
			$response    = Utils\http_request( 'GET', $version_url, null, [], $options );
			if ( ! $response->success || 200 !== $response->status_code ) {
				WP_CLI::error( sprintf( 'Failed to get current nightly version (HTTP code %d)', $response->status_code ) );
			}
			$nightly_version = trim( $response->body );

			if ( WP_CLI_VERSION !== $nightly_version ) {
				$manifest_data = null;

				// The manifest.json file, if it exists, contains information about PHP version requirements and similar.
				$response = Utils\http_request( 'GET', 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.manifest.json', null, $headers, $options );

				if ( $response->success ) {
					/**
					 * WP-CLI manifest.json data.
					 *
					 * @var object{requires_php?: string}|null $manifest_data
					 */
					$manifest_data = json_decode( $response->body );
				}

				// Release requires a newer version of PHP.
				if (
					isset( $manifest_data->requires_php ) &&
					! Comparator::greaterThanOrEqualTo( PHP_VERSION, $manifest_data->requires_php )
				) {
					$updates_unavailable[] = [
						'version'      => $nightly_version,
						'update_type'  => 'nightly',
						'package_url'  => 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.phar',
						'status'       => 'unavailable',
						'requires_php' => $manifest_data->requires_php,
					];
				} else {
					$updates['nightly'] = [
						'version'      => $nightly_version,
						'update_type'  => 'nightly',
						'package_url'  => 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.phar',
						'status'       => 'available',
						'requires_php' => isset( $manifest_data->requires_php ) ? $manifest_data->requires_php : '',
					];
				}
			}
		}

		return array_merge( $updates_unavailable, array_values( $updates ) );
	}

	/**
	 * Returns the the first element of the passed array for which the
	 * callback returns true.
	 *
	 * Polyfill for the `array_find()` function introduced in PHP 8.3.
	 *
	 * @param array    $arr      Array to search.
	 * @param callable $callback The callback function for each element in the array.
	 * @return mixed First array element for which the callback returns true, null otherwise.
	 */
	private function array_find( $arr, $callback ) {
		if ( function_exists( '\array_find' ) ) {
			// phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.array_findFound
			return \array_find( $arr, $callback );
		}

		foreach ( $arr as $key => $value ) {
			if ( $callback( $value, $key ) ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Dumps the list of global parameters, as JSON or in var_export format.
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
	public function param_dump( $_, $assoc_args ) {
		$spec = WP_CLI::get_configurator()->get_spec();

		if ( Utils\get_flag_value( $assoc_args, 'with-values' ) ) {
			$config = WP_CLI::get_configurator()->to_array();
			// Copy current config values to $spec.
			foreach ( $spec as $key => $value ) {
				$current = null;
				if ( isset( $config[0][ $key ] ) ) {
					$current = $config[0][ $key ];
				}
				$spec[ $key ]['current'] = $current;
			}
		}

		if ( 'var_export' === Utils\get_flag_value( $assoc_args, 'format' ) ) {
			var_export( $spec );
		} else {
			echo json_encode( $spec );
		}
	}

	/**
	 * Dumps the list of installed commands, as JSON.
	 *
	 * ## EXAMPLES
	 *
	 *     # Dump the list of installed commands.
	 *     $ wp cli cmd-dump
	 *     {"name":"wp","description":"Manage WordPress through the command-line.","longdesc":"\n\n## GLOBAL PARAMETERS\n\n  --path=<path>\n      Path to the WordPress files.\n\n  --ssh=<ssh>\n      Perform operation against a remote server over SSH (or a container using scheme of "docker" or "docker-compose").\n\n  --url=<url>\n      Pretend request came from given URL. In multisite, this argument is how the target site is specified. \n\n  --user=<id|login|email>\n
	 *
	 * @subcommand cmd-dump
	 */
	public function cmd_dump() {
		echo json_encode( $this->command_to_array( WP_CLI::get_root_command() ) );
	}

	/**
	 * Generates tab completion strings.
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
		$line  = substr( $assoc_args['line'], 0, $assoc_args['point'] );
		$compl = new Completions( $line );
		$compl->render();
	}

	/**
	 * Get a string representing the type of update being checked for.
	 */
	private function get_update_type_str( $assoc_args ) {
		$update_type = ' ';
		foreach ( [ 'major', 'minor', 'patch' ] as $type ) {
			if ( true === Utils\get_flag_value( $assoc_args, $type ) ) {
				$update_type = ' ' . $type . ' ';
				break;
			}
		}
		return $update_type;
	}

	/**
	 * Detects if a command exists
	 *
	 * This commands checks if a command is registered with WP-CLI.
	 * If the command is found then it returns with exit status 0.
	 * If the command doesn't exist, then it will exit with status 1.
	 *
	 * ## OPTIONS
	 * <command_name>...
	 * : The command
	 *
	 * ## EXAMPLES
	 *
	 *     # The "site delete" command is registered.
	 *     $ wp cli has-command "site delete"
	 *     $ echo $?
	 *     0
	 *
	 *     # The "foo bar" command is not registered.
	 *     $ wp cli has-command "foo bar"
	 *     $ echo $?
	 *     1
	 *
	 *     # Install a WP-CLI package if not already installed
	 *     $ if ! $(wp cli has-command doctor); then wp package install wp-cli/doctor-command; fi
	 *     Installing package wp-cli/doctor-command (dev-main || dev-master || dev-trunk)
	 *     Updating /home/person/.wp-cli/packages/composer.json to require the package...
	 *     Using Composer to install the package...
	 *     ---
	 *     Success: Package installed.
	 *
	 * @subcommand has-command
	 *
	 * @when after_wp_load
	 */
	public function has_command( $_, $assoc_args ) {

		// If command is input as a string, then explode it into array.
		$command = explode( ' ', implode( ' ', $_ ) );

		WP_CLI::halt( is_array( WP_CLI::get_runner()->find_command_to_run( $command ) ) ? 0 : 1 );
	}
}
