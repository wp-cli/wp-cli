<?php

use Mustangostang\Spyc;
/**
 * Retrieves and sets alias for WP Installs.
 *
 * Aliases are shorthand references to WordPress installs. For instance,
 * `@dev` could refer to a development install and `@prod` could refer to
 * a production install. This command gives you visibility in what
 * registered aliases you have available.
 *
 * ## EXAMPLES
 *
 *     # Get alias information.
 *     $ wp alias get @dev
 *     ssh: dev@somedeve.env:12345/home/dev/
 *
 *     # Add alias.
 *     $ wp alias add prod login@host /path/to/wordpress/install/
 *     Success: Added '@prod' alias.
 *
 *     # Update alias.
 *     $ wp alias update @prod newlogin@host /updated/wordpress/path/
 *     Success: Updated 'prod' alias.
 *
 *     # Delete alias.
 *     $ wp alias delete @prod
 *     Success: Deleted '@prod' alias.
 *
 * @package wp-cli
 * @when before_wp_load
 */
class Alias_Command extends WP_CLI_Command {

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
	 *   - var_export
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List all available aliases.
	 *     $ wp alias list
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
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		WP_CLI::print_value( WP_CLI::get_runner()->aliases, $assoc_args );
	}

	/**
	 * Gets the value for an alias.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Key for the alias.
	 *
	 * ## EXAMPLES
	 *
	 *     # Get alias.
	 *     $ wp alias get @prod
	 *     ssh: dev@somedeve.env:12345/home/dev/
	 *
	 * @subcommand get
	 */
	public function get( $args, $assoc_args ) {
		list( $alias ) = $args;
		$aliases       = Spyc::YAMLLoad( WP_CLI::get_runner()->get_global_config_path() );

		if ( ! empty( $aliases[ $alias ] ) ) {
			foreach ( $aliases[ $alias ] as $key => $value ) {
				WP_CLI::log( sprintf( ' %1$s: %2$s', $key, $value ) );
			}
		} else {
			WP_CLI::error( sprintf( 'No alias found with key \'%s\'.', $alias ) );
		}
	}

	/**
	 * Creates an alias.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Key for the alias.
	 *
	 * <login>
	 * : SSH Login.
	 *
	 * <wp_path>
	 * : WordPress install path.
	 *
	 * ## EXAMPLES
	 *
	 *     # Add alias.
	 *     $ wp alias add prod login@host /path/to/wordpress/install/
	 *     Success: Added '@prod' alias.
	 *
	 * @subcommand add
	 */
	public function add( $args, $assoc_args ) {
		$config_path = WP_CLI::get_runner()->get_global_config_path();

		try {
			if ( ! file_exists( $config_path ) ) {
				throw new Exception( 'config file does not exist.' );
			}
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		try {
			if ( ! is_writable( $config_path ) ) {
				throw new Exception( 'config file is not writable.' );
			}
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		list( $alias, $login, $wp_path ) = $args;

		$aliases = Spyc::YAMLLoad( $config_path );

		if ( ! isset( $aliases[ $alias ] ) ) {
			$aliases[ $alias ]['ssh'] = $login . ':' . $wp_path;
			$yaml_data                = Spyc::YAMLDump( $aliases );

			if ( file_put_contents( $config_path, $yaml_data ) ) {
				WP_CLI::success( sprintf( 'Added \'%s\' alias.', $alias ) );
			}
		} else {
			WP_CLI::error( sprintf( 'Key \'%s\' exists already!', $alias ) );
		}
	}

	/**
	 * Deletes an alias.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Key for the alias.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete alias.
	 *     $ wp alias delete @prod
	 *     Success: Deleted '@prod' alias.
	 *
	 * @subcommand delete
	 */
	public function delete( $args, $assoc_args ) {

		$config_path = WP_CLI::get_runner()->get_global_config_path();

		try {
			if ( ! file_exists( $config_path ) ) {
				throw new Exception( 'config file does not exist.' );
			}
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		try {
			if ( ! is_writable( $config_path ) ) {
				throw new Exception( 'config file is not writable.' );
			}
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		list( $alias ) = $args;
		$aliases       = Spyc::YAMLLoad( $config_path );

		if ( ! empty( $aliases[ $alias ] ) ) {
			unset( $aliases[ $alias ] );
			$yaml_data = Spyc::YAMLDump( $aliases );

			if ( file_put_contents( $config_path, $yaml_data ) ) {
				WP_CLI::success( sprintf( 'Deleted \'%s\' alias.', $alias ) );
			}
		} else {
			WP_CLI::error( sprintf( 'No alias found with key \'%s\'.', $alias ) );
		}
	}

	/**
	 * Updates an alias.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Key for the alias.
	 *
	 * <login>
	 * : SSH Login.
	 *
	 * <wp_path>
	 * : WordPress install path.
	 *
	 * ## EXAMPLES
	 *
	 *     # Update alias.
	 *     $ wp alias update @prod newlogin@host /updated/wordpress/path/
	 *     Success: Updated 'prod' alias.
	 *
	 * @subcommand update
	 */
	public function update( $args, $assoc_args ) {
		$config_path = WP_CLI::get_runner()->get_global_config_path();

		try {
			if ( ! file_exists( $config_path ) ) {
				throw new Exception( 'config file does not exist.' );
			}
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		try {
			if ( ! is_writable( $config_path ) ) {
				throw new Exception( 'config file is not writable.' );
			}
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		list( $alias, $login, $wp_path ) = $args;
		$aliases                         = Spyc::YAMLLoad( $config_path );

		if ( ! empty( $aliases[ $alias ] ) ) {
			$aliases[ $alias ]['ssh'] = $login . ':' . $wp_path;
			$yaml_data                = Spyc::YAMLDump( $aliases );

			if ( file_put_contents( $config_path, $yaml_data ) ) {
				WP_CLI::success( sprintf( 'Updated \'%s\' alias.', $alias ) );
			}
		} else {
			WP_CLI::error( sprintf( 'No alias found with key \'%s\'.', $alias ) );
		}
	}
}
