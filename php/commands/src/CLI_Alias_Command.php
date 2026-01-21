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
 * Learn more about [running commands remotely](https://make.wordpress.org/cli/handbook/guides/running-commands-remotely/).
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
	 *
	 * @param array                 $args      Positional arguments. Unused.
	 * @param array{format: string} $assoc_args Associative arguments.
	 */
	public function list_( $args, $assoc_args ) {
		$aliases = WP_CLI::get_runner()->aliases;

		// Add @ prefix to aliases for display (backward compatibility)
		$display_aliases = [];
		foreach ( $aliases as $alias => $value ) {
			$display_alias = '@' . $alias;
			if ( is_array( $value ) ) {
				// Check if it's a group (numeric indexed array)
				if ( isset( $value[0] ) && is_string( $value[0] ) ) {
					// It's a group, add @ prefix to each member
					$display_aliases[ $display_alias ] = array_map(
						function ( $member ) {
							return '@' . $member;
						},
						$value
					);
				} else {
					// It's a regular alias config
					$display_aliases[ $display_alias ] = $value;
				}
			} else {
				// It's a string (like the 'all' description)
				$display_aliases[ $display_alias ] = $value;
			}
		}

		WP_CLI::print_value( $display_aliases, $assoc_args );
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
	 *
	 * @param array{string} $args Positional arguments.
	 */
	public function get( $args ) {
		list( $alias ) = $args;

		// Normalize alias (remove @ prefix if present)
		$alias = ltrim( $alias, '@' );

		$aliases = WP_CLI::get_runner()->aliases;

		if ( empty( $aliases[ $alias ] ) ) {
			WP_CLI::error( "No alias found with key '@{$alias}'." );
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
	 *
	 * @param array{string} $args Positional arguments.
	 * @param array{'set-user'?: string, 'set-url'?: string, 'set-path'?: string, 'set-ssh'?: string, 'set-http'?: string, grouping?: string, config?: string} $assoc_args Associative arguments.
	 */
	public function add( $args, $assoc_args ) {

		$config = ( ! empty( $assoc_args['config'] ) ? $assoc_args['config'] : 'global' );

		list( $config_path, $aliases ) = $this->get_aliases_data( $config, '', true );

		$this->validate_config_file( $config_path );

		$alias = $args[0];

		/**
		 * @var string|null $grouping
		 */
		$grouping = Utils\get_flag_value( $assoc_args, 'grouping' );

		$this->validate_input( $assoc_args, $grouping );

		$existing_key = $this->find_alias_key( $aliases, $alias );
		if ( null !== $existing_key ) {
			WP_CLI::error( "Key '@" . $this->normalize_alias( $alias ) . "' exists already." );
		}

		// When adding new aliases, normalize the key (no @ prefix)
		$normalized_alias = $this->normalize_alias( $alias );

		if ( null === $grouping ) {
			$aliases = $this->build_aliases( $aliases, $normalized_alias, $assoc_args, false );
		} else {
			$aliases = $this->build_aliases( $aliases, $normalized_alias, $assoc_args, true, $grouping );
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
	 *
	 * @param array{string}          $args       Positional arguments.
	 * @param array{config?: string} $assoc_args Associative arguments
	 */
	public function delete( $args, $assoc_args ) {

		list( $alias ) = $args;

		$config = ( ! empty( $assoc_args['config'] ) ? $assoc_args['config'] : '' );

		list( $config_path, $aliases ) = $this->get_aliases_data( $config, $alias );

		$this->validate_config_file( $config_path );

		$alias_key = $this->find_alias_key( $aliases, $alias );
		if ( null === $alias_key ) {
			WP_CLI::error( "No alias found with key '@" . $this->normalize_alias( $alias ) . "'." );
		}

		unset( $aliases[ $alias_key ] );
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
	 *
	 * @param array{string} $args Positional arguments.
	 * @param array{'set-user'?: string, 'set-url'?: string, 'set-path'?: string, 'set-ssh'?: string, 'set-http'?: string, grouping?: string, config?: string} $assoc_args Associative arguments.
	 */
	public function update( $args, $assoc_args ) {

		$config = ( ! empty( $assoc_args['config'] ) ? $assoc_args['config'] : '' );
		$alias  = $args[0];

		/**
		 * @var string|null $grouping
		 */
		$grouping = Utils\get_flag_value( $assoc_args, 'grouping' );

		list( $config_path, $aliases ) = $this->get_aliases_data( $config, $alias, true );

		$this->validate_config_file( $config_path );

		$this->validate_input( $assoc_args, $grouping );

		$alias_key = $this->find_alias_key( $aliases, $alias );
		if ( null === $alias_key ) {
			WP_CLI::error( "No alias found with key '@" . $this->normalize_alias( $alias ) . "'." );
		}

		// For updates, we need to work with the actual YAML key
		// Pass the alias_key to build_aliases which will be normalized internally
		// But we need to remove the old key and add with the new one if structure changed
		if ( null === $grouping ) {
			$aliases = $this->build_aliases( $aliases, $alias_key, $assoc_args, false, '', true );
		} else {
			$aliases = $this->build_aliases( $aliases, $alias_key, $assoc_args, true, $grouping, true );
		}

		$this->process_aliases( $aliases, $alias, $config_path, 'Updated' );
	}

	/**
	 * Check whether an alias is a group.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Key for the alias.
	 *
	 * ## EXAMPLES
	 *
	 *     # Checks whether the alias is a group; exit status 0 if it is, otherwise 1.
	 *     $ wp cli alias is-group @prod
	 *     $ echo $?
	 *     1
	 *
	 * @subcommand is-group
	 */
	public function is_group( $args, $assoc_args = array() ) {
		$alias = ltrim( $args[0], '@' );

		$aliases = WP_CLI::get_runner()->aliases;

		if ( empty( $aliases[ $alias ] ) ) {
			WP_CLI::error( "No alias found with key '@{$alias}'." );
		}

		// how do we know the alias is a group?
		// + array keys are numeric
		// + array values are strings (group members)

		$first_item     = $aliases[ $alias ];
		$first_item_key = key( $first_item );

		if ( is_numeric( $first_item_key ) ) {
			WP_CLI::halt( 0 );
		}
		WP_CLI::halt( 1 );
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

			$is_global_alias  = null !== $this->find_alias_key( $global_aliases, $alias );
			$is_project_alias = null !== $this->find_alias_key( $project_aliases, $alias );

			if ( $is_global_alias && $is_project_alias ) {
				WP_CLI::error( "Key '@" . $this->normalize_alias( $alias ) . "' found in more than one path. Please pass --config param." );
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
	 */
	private function validate_config_file( $config_path ): void {
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
	 * @return array<string, array<string, mixed>>
	 */
	private function build_aliases( $aliases, $alias, $assoc_args, $is_grouping, $grouping = '', $is_update = false ) {
		// For updates, we might receive @foo or foo depending on YAML format
		// Normalize it for consistency
		$normalized_alias = $this->normalize_alias( $alias );

		if ( $is_grouping ) {
			$valid_assoc_args = [ 'config', 'grouping' ];
			$invalid_args     = array_diff( array_keys( $assoc_args ), $valid_assoc_args );

			// Check for invalid args.
			if ( ! empty( $invalid_args ) ) {
				$args_info = implode( ',', $invalid_args );
				WP_CLI::error( "--grouping argument works alone. Found invalid arg(s) '$args_info'." );
			}
		}

		// Validate BEFORE modifying the aliases array
		if ( $is_update ) {
			$this->validate_alias_type( $aliases, $alias, $assoc_args, $grouping );
		}

		// If updating, we need to preserve existing data and only update specified fields
		$existing_data = [];
		if ( $is_update ) {
			// Find the existing alias data to preserve it
			$alias_key = $this->find_alias_key( $aliases, $alias );
			if ( null !== $alias_key ) {
				// Get existing data based on format
				if ( isset( $aliases['aliases'][ $normalized_alias ] ) ) {
					$existing_data = $aliases['aliases'][ $normalized_alias ];
				} elseif ( isset( $aliases[ $alias_key ] ) ) {
					$existing_data = $aliases[ $alias_key ];
				}
			}

			// Remove the old key structure
			if ( isset( $aliases[ $alias ] ) ) {
				unset( $aliases[ $alias ] );
			}
			// Also check if it's in the @format
			$at_key = '@' . $normalized_alias;
			if ( isset( $aliases[ $at_key ] ) ) {
				unset( $aliases[ $at_key ] );
			}
			// Check if it's under aliases:
			if ( isset( $aliases['aliases'][ $normalized_alias ] ) ) {
				unset( $aliases['aliases'][ $normalized_alias ] );
			}
		}

		if ( ! $is_grouping ) {
			// Start with existing data for updates, or empty array for new aliases
			if ( ! isset( $aliases[ $normalized_alias ] ) ) {
				$aliases[ $normalized_alias ] = $existing_data;
			}

			foreach ( $assoc_args as $key => $value ) {
				if ( strpos( $key, 'set-' ) !== false ) {
					$alias_key_info = explode( '-', $key );
					$alias_key      = empty( $alias_key_info[1] ) ? '' : $alias_key_info[1];
					if ( ! empty( $alias_key ) && ! empty( $value ) ) {
						$aliases[ $normalized_alias ][ $alias_key ] = $value;
					}
				}
			}
		} elseif ( ! empty( $grouping ) ) {
			$group_alias_list             = explode( ',', $grouping );
			$group_alias                  = array_map(
				function ( $current_alias ) {
					// Remove @ prefix if present
					return ltrim( $current_alias, '@' );
				},
				$group_alias_list
			);
			$aliases[ $normalized_alias ] = $group_alias;
		}

		return $aliases;
	}

	/**
	 * Validate input of passed arguments.
	 *
	 * @param array       $assoc_args Arguments array.
	 * @param string|null $grouping   Grouping argument value.
	 *
	 * @throws ExitException
	 */
	private function validate_input( $assoc_args, $grouping ) {
		// Check if valid arguments were passed.
		$arg_match = (array) preg_grep( '/^set-(\w+)/i', array_keys( $assoc_args ) );

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
	 * @param string $alias      Alias Name (can be normalized or with @).
	 * @param array  $assoc_args Arguments array.
	 * @param string $grouping   Grouping argument value.
	 *
	 * @throws ExitException
	 */
	private function validate_alias_type( $aliases, $alias, $assoc_args, $grouping ) {

		// Find the actual key in YAML
		$alias_key = $this->find_alias_key( $aliases, $alias );
		if ( null === $alias_key ) {
			$alias_data = null;
		} elseif ( isset( $aliases['aliases'] ) && isset( $aliases['aliases'][ $alias_key ] ) ) {
			$alias_data = $aliases['aliases'][ $alias_key ];
		} else {
			$alias_data = $aliases[ $alias_key ];
		}

		// Handle null or non-array data
		if ( ! is_array( $alias_data ) ) {
			$alias_data = [];
		}

		// Check if this is a group alias by looking for numeric keys with string values
		// Group aliases are stored as arrays like ['foo', 'bar'] without @ prefix
		$is_group_alias = false;
		if ( ! empty( $alias_data ) ) {
			$numeric_keys = array_filter( array_keys( $alias_data ), 'is_numeric' );
			if ( count( $numeric_keys ) === count( $alias_data ) ) {
				// All keys are numeric, so this is a group alias
				$is_group_alias = true;
			}
		}

		$arg_match = preg_grep( '/^set-(\w+)/i', array_keys( $assoc_args ) );

		if ( $is_group_alias && ! empty( $arg_match ) ) {
			WP_CLI::error( 'Trying to update group alias with invalid arguments.' );
		} elseif ( ! $is_group_alias && ! empty( $grouping ) ) {
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
		$alias = $this->normalize_alias( $alias );

		// Convert aliases to use the new 'aliases:' format for better cross-platform compatibility
		// Move any @-prefixed keys into the aliases: section
		$yaml_data       = [];
		$aliases_section = [];

		foreach ( $aliases as $key => $value ) {
			// Skip special config keys that aren't aliases
			if ( in_array( $key, [ 'require', 'path', '_', 'url', 'user', 'ssh', 'http' ], true ) ) {
				$yaml_data[ $key ] = $value;
			} elseif ( 0 === strpos( $key, '@' ) ) {
				// Convert @foo to aliases: { foo: } format
				$normalized_key                     = substr( $key, 1 );
				$aliases_section[ $normalized_key ] = $value;
			} elseif ( 'aliases' === $key ) {
				// Already in aliases format, merge it
				if ( is_array( $value ) ) {
					$aliases_section = array_merge( $aliases_section, $value );
				}
			} elseif ( is_array( $value ) ) {
				// This is an alias (either config or group), add to aliases section
				$aliases_section[ $key ] = $value;
			} else {
				// Non-alias config value
				$yaml_data[ $key ] = $value;
			}
		}

		// Add the aliases section if we have any
		if ( ! empty( $aliases_section ) ) {
			$yaml_data['aliases'] = $aliases_section;
		}

		// Convert data to YAML string.
		$yaml_output = Spyc::YAMLDump( $yaml_data );

		// Add data in config file.
		if ( file_put_contents( $config_path, $yaml_output ) ) {
			WP_CLI::success( "$operation '{$alias}' alias." );
		}
	}

	/**
	 * Normalize the alias to an expected format.
	 *
	 * - Remove @ if present.
	 *
	 * @param string $alias Name of alias.
	 */
	private function normalize_alias( $alias ) {
		// Remove the @ prefix if present for storage
		// See: https://github.com/wp-cli/wp-cli/issues/5391
		return ltrim( $alias, '@' );
	}

	/**
	 * Find the actual key used for an alias in YAML data.
	 *
	 * Handles both @foo format and aliases: { foo: } format.
	 *
	 * @param array  $yaml_data The raw YAML data.
	 * @param string $alias     The alias name (with or without @).
	 * @return string|null      The actual key in YAML, or null if not found.
	 */
	private function find_alias_key( $yaml_data, $alias ) {
		$normalized = $this->normalize_alias( $alias );

		// Check for @foo format
		$at_key = '@' . $normalized;
		if ( array_key_exists( $at_key, $yaml_data ) ) {
			return $at_key;
		}

		// Check for aliases: { foo: } format
		if ( isset( $yaml_data['aliases'] ) && is_array( $yaml_data['aliases'] ) ) {
			if ( array_key_exists( $normalized, $yaml_data['aliases'] ) ) {
				return $normalized;
			}
		}

		return null;
	}
}
