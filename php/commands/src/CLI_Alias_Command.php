<?php

/**
 * Retrieves, sets and updates aliases for WordPress Installations.
 */

use Mustangostang\Spyc;
use WP_CLI\ExitException;
use WP_CLI\Utils;

/**
 * Retrieves, sets and updates aliases for WordPress Installations.
 *
 * Aliases are shorthand references to WordPress installs. For instance,
 * `@dev` could refer to a development install and `@prod` could refer to a production install.
 * This command gives you and option to add, update and delete, the registered aliases you have available.
 *
 * ## EXAMPLES
 *
 *     # List alias information.
 *     $ wp cli alias list
 *     list
 *     ---
 *     @all: Run command against every registered alias.
 *     @local:
 *       user: wpcli
 *       path: /Users/wpcli/sites/testsite
 *
 *     # Get alias information.
 *     $ wp cli alias get @dev
 *     ssh: dev@somedeve.env:12345/home/dev/
 *
 *     # Add alias.
 *     $ wp cli alias add @prod --set-ssh=login@host --set-path=/path/to/wordpress/install/ --set-user=wpcli
 *     Success: Added '@prod' alias.
 *
 *     # Update alias.
 *     $ wp cli alias update @prod --set-user=newuser --set-path=/new/path/to/wordpress/install/
 *     Success: Updated 'prod' alias.
 *
 *     # Delete alias.
 *     $ wp cli alias delete @prod
 *     Success: Deleted '@prod' alias.
 *
 * @package wp-cli
 * @when    before_wp_load
 */
class CLI_Alias_Command extends WP_CLI_Command {

	/**
	 * Lists available WP-CLI aliases.
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
	 *     $ wp cli alias list
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
	 *     $ wp cli alias get @prod
	 *     ssh: dev@somedeve.env:12345/home/dev/
	 */
	public function get( $args, $assoc_args ) {
		list( $alias ) = $args;

		$aliases = WP_CLI::get_runner()->aliases;

		if ( empty( $aliases[ $alias ] ) ) {
			WP_CLI::error( "No alias found with key '{$alias}'." );
		}

		foreach ( $aliases[ $alias ] as $key => $value ) {
			WP_CLI::log( "{$key}: {$value}" );
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
	 * [--set-user=<user>]
	 * : Set user for alias.
	 *
	 * [--set-url=<url>]
	 * : Set url for alias.
	 *
	 * [--set-path=<path>]
	 * : Set path for alias.
	 *
	 * [--set-ssh=<ssh>]
	 * : Set ssh for alias.
	 *
	 * [--set-http=<http>]
	 * : Set http for alias.
	 *
	 * [--grouping=<grouping>]
	 * : For grouping multiple aliases.
	 *
	 * [--config=<config>]
	 * : Config file to be considered for operations.
	 * ---
	 * default: global
	 * options:
	 *   - global
	 *   - project
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Add alias to global config.
	 *     $ wp cli alias add @prod  --set-ssh=login@host --set-path=/path/to/wordpress/install/ --set-user=wpcli
	 *     Success: Added '@prod' alias.
	 *
	 *     # Add alias to project config.
	 *     $ wp cli alias add @prod --set-ssh=login@host --set-path=/path/to/wordpress/install/ --set-user=wpcli --config=project
	 *     Success: Added '@prod' alias.
	 *
	 *     # Add group of aliases.
	 *     $ wp cli alias add @multiservers --grouping=servera,serverb
	 *     Success: Added '@multiservers' alias.
	 */
	public function add( $args, $assoc_args ) {

		$config = ( ! empty( $assoc_args['config'] ) ? $assoc_args['config'] : 'global' );

		list( $config_path, $aliases ) = $this->get_aliases_data( $config, '', true );

		$this->validate_config_file( $config_path );

		$alias    = $args[0];
		$grouping = Utils\get_flag_value( $assoc_args, 'grouping' );

		$this->validate_input( $assoc_args, $grouping );

		if ( isset( $aliases[ $alias ] ) ) {
			WP_CLI::error( "Key '{$alias}' exists already." );
		}

		if ( null === $grouping ) {
			$aliases = $this->build_aliases( $aliases, $alias, $assoc_args, false );
		} else {
			$aliases = $this->build_aliases( $aliases, $alias, $assoc_args, true, $grouping );
		}

		$this->process_aliases( $aliases, $alias, $config_path, 'Added' );
	}

	/**
	 * Deletes an alias.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Key for the alias.
	 *
	 * [--config=<config>]
	 * : Config file to be considered for operations.
	 * ---
	 * options:
	 *   - global
	 *   - project
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete alias.
	 *     $ wp cli alias delete @prod
	 *     Success: Deleted '@prod' alias.
	 *
	 *     # Delete project alias.
	 *     $ wp cli alias delete @prod --config=project
	 *     Success: Deleted '@prod' alias.
	 */
	public function delete( $args, $assoc_args ) {

		list( $alias ) = $args;

		$config = ( ! empty( $assoc_args['config'] ) ? $assoc_args['config'] : '' );

		list( $config_path, $aliases ) = $this->get_aliases_data( $config, $alias );

		$this->validate_config_file( $config_path );

		if ( empty( $aliases[ $alias ] ) ) {
			WP_CLI::error( "No alias found with key '{$alias}'." );
		}

		unset( $aliases[ $alias ] );
		$this->process_aliases( $aliases, $alias, $config_path, 'Deleted' );

	}

	/**
	 * Updates an alias.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Key for the alias.
	 *
	 * [--set-user=<user>]
	 * : Set user for alias.
	 *
	 * [--set-url=<url>]
	 * : Set url for alias.
	 *
	 * [--set-path=<path>]
	 * : Set path for alias.
	 *
	 * [--set-ssh=<ssh>]
	 * : Set ssh for alias.
	 *
	 * [--set-http=<http>]
	 * : Set http for alias.
	 *
	 * [--grouping=<grouping>]
	 * : For grouping multiple aliases.
	 *
	 * [--config=<config>]
	 * : Config file to be considered for operations.
	 * ---
	 * options:
	 *   - global
	 *   - project
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Update alias.
	 *     $ wp cli alias update @prod --set-user=newuser --set-path=/new/path/to/wordpress/install/
	 *     Success: Updated 'prod' alias.
	 *
	 *     # Update project alias.
	 *     $ wp cli alias update @prod --set-user=newuser --set-path=/new/path/to/wordpress/install/ --config=project
	 *     Success: Updated 'prod' alias.
	 */
	public function update( $args, $assoc_args ) {

		$config   = ( ! empty( $assoc_args['config'] ) ? $assoc_args['config'] : '' );
		$alias    = $args[0];
		$grouping = Utils\get_flag_value( $assoc_args, 'grouping' );

		list( $config_path, $aliases ) = $this->get_aliases_data( $config, $alias, true );

		$this->validate_config_file( $config_path );

		$this->validate_input( $assoc_args, $grouping );

		if ( empty( $aliases[ $alias ] ) ) {
			WP_CLI::error( "No alias found with key '{$alias}'." );
		}

		if ( null === $grouping ) {
			$aliases = $this->build_aliases( $aliases, $alias, $assoc_args, false, '', true );
		} else {
			$aliases = $this->build_aliases( $aliases, $alias, $assoc_args, true, $grouping, true );
		}

		$this->process_aliases( $aliases, $alias, $config_path, 'Updated' );
	}

	/**
	 * Get config path and aliases data based on config type.
	 *
	 * @param string $config             Type of config to get data from.
	 * @param string $alias              Alias to be used for Add/Update/Delete.
	 * @param bool   $create_config_file Optional. If a config file doesn't exist,
	 *                                   should it be created? Defaults to false.
	 *
	 * @return array Config Path and Aliases in it.
	 * @throws ExitException
	 */
	private function get_aliases_data( $config, $alias, $create_config_file = false ) {

		$global_config_path = WP_CLI::get_runner()->get_global_config_path( $create_config_file );
		$global_aliases     = Spyc::YAMLLoad( $global_config_path );

		$project_config_path = WP_CLI::get_runner()->get_project_config_path();
		$project_aliases     = Spyc::YAMLLoad( $project_config_path );

		if ( 'global' === $config ) {
			$config_path = $global_config_path;
			$aliases     = $global_aliases;
		} elseif ( 'project' === $config ) {
			$config_path = $project_config_path;
			$aliases     = $project_aliases;
		} else {

			$is_global_alias  = array_key_exists( $alias, $global_aliases );
			$is_project_alias = array_key_exists( $alias, $project_aliases );

			if ( $is_global_alias && $is_project_alias ) {
				WP_CLI::error( "Key '{$alias}' found in more than one path. Please pass --config param." );
			} elseif ( $is_global_alias ) {
				$config_path = $global_config_path;
				$aliases     = $global_aliases;
			} else {
				$config_path = $project_config_path;
				$aliases     = $project_aliases;
			}
		}

		return [ $config_path, $aliases ];

	}

	/**
	 * Check if the config file exists and is writable.
	 *
	 * @param string $config_path Path to config file.
	 *
	 * @return void
	 */
	private function validate_config_file( $config_path ) {
		if ( ! file_exists( $config_path ) || ! is_writable( $config_path ) ) {
			WP_CLI::error( "Config file does not exist: {$config_path}" );
		}
	}

	/**
	 * Return aliases array.
	 *
	 * @param array  $aliases     Current aliases data.
	 * @param string $alias       Name of alias.
	 * @param array  $assoc_args  Associative arguments.
	 * @param bool   $is_grouping Check if its a grouping operation.
	 * @param string $grouping    Grouping value.
	 * @param bool   $is_update   Is this an update operation?
	 *
	 * @return mixed
	 */
	private function build_aliases( $aliases, $alias, $assoc_args, $is_grouping, $grouping = '', $is_update = false ) {

		if ( $is_grouping ) {
			$valid_assoc_args = [ 'config', 'grouping' ];
			$invalid_args     = array_diff( array_keys( $assoc_args ), $valid_assoc_args );

			// Check for invalid args.
			if ( ! empty( $invalid_args ) ) {
				$args_info = implode( ',', $invalid_args );
				WP_CLI::error( "--grouping argument works alone. Found invalid arg(s) '$args_info'." );
			}
		}

		if ( $is_update ) {
			$this->validate_alias_type( $aliases, $alias, $assoc_args, $grouping );
		}

		if ( ! $is_grouping ) {
			foreach ( $assoc_args as $key => $value ) {
				if ( strpos( $key, 'set-' ) !== false ) {
					$alias_key_info = explode( '-', $key );
					$alias_key      = empty( $alias_key_info[1] ) ? '' : $alias_key_info[1];
					if ( ! empty( $alias_key ) && ! empty( $value ) ) {
						$aliases[ $alias ][ $alias_key ] = $value;
					}
				}
			}
		} else {

			if ( ! empty( $grouping ) ) {
				$group_alias_list  = explode( ',', $grouping );
				$group_alias       = array_map(
					function ( $current_alias ) {
						return '@' . ltrim( $current_alias, '@' );
					},
					$group_alias_list
				);
				$aliases[ $alias ] = $group_alias;
			}
		}

		return $aliases;
	}

	/**
	 * Validate input of passed arguments.
	 *
	 * @param array  $assoc_args Arguments array.
	 * @param string $grouping   Grouping argument value.
	 *
	 * @throws ExitException
	 */
	private function validate_input( $assoc_args, $grouping ) {
		// Check if valid arguments were passed.
		$arg_match = preg_grep( '/^set-(\w+)/i', array_keys( $assoc_args ) );

		// Verify passed-arguments.
		if ( empty( $grouping ) && empty( $arg_match ) ) {
			WP_CLI::error( 'No valid arguments passed.' );
		}

		// Check whether passed arguments contain value or not.
		$assoc_arg_values = array_filter( array_intersect_key( $assoc_args, array_flip( $arg_match ) ) );

		if ( empty( $grouping ) && empty( $assoc_arg_values ) ) {
			WP_CLI::error( 'No value passed to arguments.' );
		}
	}

	/**
	 * Validate alias type before update.
	 *
	 * @param array  $aliases    Existing aliases data.
	 * @param string $alias      Alias Name.
	 * @param array  $assoc_args Arguments array.
	 * @param string $grouping   Grouping argument value.
	 *
	 * @throws ExitException
	 */
	private function validate_alias_type( $aliases, $alias, $assoc_args, $grouping ) {

		$alias_data = $aliases[ $alias ];

		$group_aliases_match = preg_grep( '/^@(\w+)/i', $alias_data );
		$arg_match           = preg_grep( '/^set-(\w+)/i', array_keys( $assoc_args ) );

		if ( ! empty( $group_aliases_match ) && ! empty( $arg_match ) ) {
			WP_CLI::error( 'Trying to update group alias with invalid arguments.' );
		} elseif ( empty( $group_aliases_match ) && ! empty( $grouping ) ) {
			WP_CLI::error( 'Trying to update simple alias with invalid --grouping argument.' );
		}
	}

	/**
	 * Save aliases data to config file.
	 *
	 * @param array  $aliases     Current aliases data.
	 * @param string $alias       Name of alias.
	 * @param string $config_path Path to config file.
	 * @param string $operation   Current operation string fro message.
	 */
	private function process_aliases( $aliases, $alias, $config_path, $operation = '' ) {
		// Convert data to YAML string.
		$yaml_data = Spyc::YAMLDump( $aliases );

		// Add data in config file.
		if ( file_put_contents( $config_path, $yaml_data ) ) {
			WP_CLI::success( "$operation '{$alias}' alias." );
		}
	}
}
