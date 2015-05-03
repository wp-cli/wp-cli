<?php

use \WP_CLI\Utils;

/**
 * Manage plugins.
 *
 * @package wp-cli
 */
class Plugin_Command extends \WP_CLI\CommandWithUpgrade {

	protected $item_type = 'plugin';
	protected $upgrade_refresh = 'wp_update_plugins';
	protected $upgrade_transient = 'update_plugins';

	protected $obj_fields = array(
		'name',
		'status',
		'update',
		'version'
	);

	function __construct() {
		require_once ABSPATH.'wp-admin/includes/plugin.php';
		require_once ABSPATH.'wp-admin/includes/plugin-install.php';

		parent::__construct();

		$this->fetcher = new \WP_CLI\Fetchers\Plugin;
	}

	protected function get_upgrader_class( $force ) {
		return $force ? '\\WP_CLI\\DestructivePluginUpgrader' : 'Plugin_Upgrader';
	}

	/**
	 * See the status of one or all plugins.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>]
	 * : A particular plugin to show the status for.
	 */
	function status( $args ) {
		parent::status( $args );
	}

	/**
	 * Search the wordpress.org plugin repository.
	 *
	 * ## OPTIONS
	 *
	 * <search>
	 * : The string to search for.
	 *
	 * [--per-page=<per-page>]
	 * : Optional number of results to display. Defaults to 10.
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each plugin.
	 *
	 * [--fields=<fields>]
	 * : Ask for specific fields from the API. Defaults to name,slug,author_profile,rating. Acceptable values:
	 *
	 *     **name**: Plugin Name
	 *     **slug**: Plugin Slug
	 *     **version**: Current Version Number
	 *     **author**: Plugin Author
	 *     **author_profile**: Plugin Author Profile
	 *     **contributors**: Plugin Contributors
	 *     **requires**: Plugin Minimum Requirements
	 *     **tested**: Plugin Tested Up To
	 *     **compatibility**: Plugin Compatible With
	 *     **rating**: Plugin Rating
	 *     **num_ratings**: Number of Plugin Ratings
	 *     **homepage**: Plugin Author's Homepage
	 *     **description**: Plugin's Description
	 *     **short_description**: Plugin's Short Description
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp plugin search dsgnwrks --per-page=20 --format=json
	 *
	 *     wp plugin search dsgnwrks --fields=name,version,slug,rating,num_ratings
	 */
	public function search( $args, $assoc_args ) {
		parent::_search( $args, $assoc_args );
	}

	protected function status_single( $args ) {
		$plugin = $this->fetcher->get_check( $args[0] );
		$file = $plugin->file;

		$details = $this->get_details( $file );

		$status = $this->format_status( $this->get_status( $file ), 'long' );

		$version = $details['Version'];

		if ( $this->has_update( $file ) )
			$version .= ' (%gUpdate available%n)';

		echo WP_CLI::colorize( \WP_CLI\Utils\mustache_render( 'plugin-status.mustache', array(
			'slug' => Utils\get_plugin_name( $file ),
			'status' => $status,
			'version' => $version,
			'name' => $details['Name'],
			'author' => $details['Author'],
			'description' => $details['Description']
		) ) );
	}

	protected function get_all_items() {
		$items = $this->get_item_list();

		foreach ( get_mu_plugins() as $file => $mu_plugin ) {
			$mu_version = '';
			if ( ! empty( $mu_plugin['Version'] ) ) {
				$mu_version = $mu_plugin['Version'];
			}

			$items[ $file ] = array(
				'name'           => Utils\get_plugin_name( $file ),
				'status'         => 'must-use',
				'update'         => false,
				'update_version' => NULL,
				'update_package' => NULL,
				'version'        => $mu_version,
				'update_id'      => '',
				'title'          => '',
				'description'    => '',
			);
		}

		return $items;
	}

	/**
	 * Activate a plugin.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>...]
	 * : One or more plugins to activate.
	 *
	 * [--all]
	 * : If set, all plugins will be activated.
	 *
	 * [--network]
	 * : If set, the plugin will be activated for the entire multisite network.
	 */
	function activate( $args, $assoc_args = array() ) {
		$network_wide = \WP_CLI\Utils\get_flag_value( $assoc_args, 'network' );

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) ) {
			$args = array_map( function( $file ){
				return Utils\get_plugin_name( $file );
			}, array_keys( get_plugins() ) );
		}

		foreach ( $this->fetcher->get_many( $args ) as $plugin ) {
			$status = $this->get_status( $plugin->file );
			// Network-active is the highest level of activation status
			if ( 'active-network' === $status ) {
				WP_CLI::warning( "Plugin '{$plugin->name}' is already network active." );
				continue;
			}
			// Don't reactivate active plugins, but do let them become network-active
			if ( ! $network_wide && 'active' === $status ) {
				WP_CLI::warning( "Plugin '{$plugin->name}' is already active." );
				continue;
			}

			// Plugins need to be deactivated before being network activated
			if ( $network_wide && 'active' === $status ) {
				deactivate_plugins( $plugin->file, false, false );
			}

			activate_plugin( $plugin->file, '', $network_wide );

			$this->active_output( $plugin->name, $plugin->file, $network_wide, "activate" );
		}

	}

	/**
	 * Deactivate a plugin.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>...]
	 * : One or more plugins to deactivate.
	 *
	 * [--uninstall]
	 * : Uninstall the plugin after deactivation.
	 *
	 * [--all]
	 * : If set, all plugins will be deactivated.
	 *
	 * [--network]
	 * : If set, the plugin will be deactivated for the entire multisite network.
	 */
	function deactivate( $args, $assoc_args = array() ) {
		$network_wide = \WP_CLI\Utils\get_flag_value( $assoc_args, 'network' );
		$disable_all = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' );

		if ( $disable_all ) {
			$args = array_map( function( $file ){
				return Utils\get_plugin_name( $file );
			}, array_keys( get_plugins() ) );
		}

		foreach ( $this->fetcher->get_many( $args ) as $plugin ) {

			$status = $this->get_status( $plugin->file );
			// Network active plugins must be explicitly deactivated
			if ( ! $network_wide && 'active-network' === $status ) {
				WP_CLI::warning( "Plugin '{$plugin->name}' is network active and must be deactivated with --network flag." );
				continue;
			}

			if ( ! in_array( $status, array( 'active', 'active-network' ) ) ) {
				WP_CLI::warning( "Plugin '{$plugin->name}' isn't active." );
				continue;
			}

			deactivate_plugins( $plugin->file, false, $network_wide );

			$this->active_output( $plugin->name, $plugin->file, $network_wide, "deactivate" );

			if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'uninstall' ) ) {
				WP_CLI::log( "Uninstalling '{$plugin->name}'..." );
				$this->uninstall( array( $plugin->name ) );
			}

		}
	}

	/**
	 * Toggle a plugin's activation state.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>...
	 * : One or more plugins to toggle.
	 *
	 * [--network]
	 * : If set, the plugin will be toggled for the entire multisite network.
	 */
	function toggle( $args, $assoc_args = array() ) {
		$network_wide = \WP_CLI\Utils\get_flag_value( $assoc_args, 'network' );

		foreach ( $this->fetcher->get_many( $args ) as $plugin ) {
			if ( $this->check_active( $plugin->file, $network_wide ) ) {
				$this->deactivate( array( $plugin->name ), $assoc_args );
			} else {
				$this->activate( array( $plugin->name ), $assoc_args );
			}
		}
	}

	/**
	 * Get the path to a plugin or to the plugin directory.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>]
	 * : The plugin to get the path to. If not set, will return the path to the
	 * plugins directory.
	 *
	 * [--dir]
	 * : If set, get the path to the closest parent directory, instead of the
	 * plugin file.
	 *
	 * ## EXAMPLES
	 *
	 *     cd $(wp plugin path)
	 */
	function path( $args, $assoc_args ) {
		$path = untrailingslashit( WP_PLUGIN_DIR );

		if ( !empty( $args ) ) {
			$plugin = $this->fetcher->get_check( $args[0] );
			$path .= '/' . $plugin->file;

			if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'dir' ) )
				$path = dirname( $path );
		}

		WP_CLI::line( $path );
	}

	protected function install_from_repo( $slug, $assoc_args ) {
		$api = plugins_api( 'plugin_information', array( 'slug' => $slug ) );

		if ( is_wp_error( $api ) ) {
			return $api;
		}

		if ( isset( $assoc_args['version'] ) ) {
			self::alter_api_response( $api, $assoc_args['version'] );
		}

		$status = install_plugin_install_status( $api );

		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'force' ) && 'install' != $status['status'] ) {
			// We know this will fail, so avoid a needless download of the package.
			return new WP_Error( 'already_installed', 'Plugin already installed.' );
		}

		WP_CLI::log( sprintf( 'Installing %s (%s)', html_entity_decode( $api->name, ENT_QUOTES ), $api->version ) );
		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'version' ) != 'dev' ) {
			WP_CLI::get_http_cache_manager()->whitelist_package( $api->download_link, $this->item_type, $api->slug, $api->version );
		}
		$result = $this->get_upgrader( $assoc_args )->install( $api->download_link );

		return $result;
	}

	/**
	 * Update one or more plugins.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>...]
	 * : One or more plugins to update.
	 *
	 * [--all]
	 * : If set, all plugins that have updates will be updated.
	 *
	 * [--version=<version>]
	 * : If set, the plugin will be updated to the specified version.
	 *
	 * [--dry-run]
	 * : Preview which plugins would be updated.
	 *
	 * ## EXAMPLES
	 *
	 *     wp plugin update bbpress --version=dev
	 *
	 *     wp plugin update --all
	 */
	function update( $args, $assoc_args ) {
		if ( isset( $assoc_args['version'] ) ) {
			foreach ( $this->fetcher->get_many( $args ) as $plugin ) {
				$this->_delete( $plugin );

				$assoc_args['force'] = 1;
				$this->install( array( $plugin->name ), $assoc_args );
			}
		} else {
			parent::update_many( $args, $assoc_args );
		}
	}

	protected function get_item_list() {
		$items = $duplicate_names = array();

		foreach ( get_plugins() as $file => $details ) {
			$update_info = $this->get_update_info( $file );

			$name = Utils\get_plugin_name( $file );
			if ( ! isset( $duplicate_names[ $name ] ) ) {
				$duplicate_names[ $name ] = array();
			}
			$duplicate_names[ $name ][] = $file;
			$items[ $file ] = array(
				'name' => $name,
				'status' => $this->get_status( $file ),
				'update' => (bool) $update_info,
				'update_version' => $update_info['new_version'],
				'update_package' => $update_info['package'],
				'version' => $details['Version'],
				'update_id' => $file,
				'title' => $details['Name'],
				'description' => $details['Description'],
			);
		}

		foreach( $duplicate_names as $name => $files ) {
			if ( count( $files ) <= 1 ) {
				continue;
			}
			foreach( $files as $file ) {
				$items[ $file ]['name'] = str_replace( '.' . pathinfo( $file, PATHINFO_EXTENSION ), '', $file ); 
			}
		}

		return $items;
	}

	protected function filter_item_list( $items, $args ) {
		$basenames = wp_list_pluck( $this->fetcher->get_many( $args ), 'file' );
		return \WP_CLI\Utils\pick_fields( $items, $basenames );
	}

	/**
	 * Install a plugin.
	 *
	 * ## OPTIONS
	 *
	 * <plugin|zip|url>...
	 * : A plugin slug, the path to a local zip file, or URL to a remote zip file.
	 *
	 * [--version=<version>]
	 * : If set, get that particular version from wordpress.org, instead of the
	 * stable version.
	 *
	 * [--force]
	 * : If set, the command will overwrite any installed version of the plugin, without prompting
	 * for confirmation.
	 *
	 * [--activate]
	 * : If set, the plugin will be activated immediately after install.
	 *
	 * [--activate-network]
	 * : If set, the plugin will be network activated immediately after install
	 *
	 * ## EXAMPLES
	 *
	 *     # Install the latest version from wordpress.org and activate
	 *     wp plugin install bbpress --activate
	 *
	 *     # Install the development version from wordpress.org
	 *     wp plugin install bbpress --version=dev
	 *
	 *     # Install from a local zip file
	 *     wp plugin install ../my-plugin.zip
	 *
	 *     # Install from a remote zip file
	 *     wp plugin install http://s3.amazonaws.com/bucketname/my-plugin.zip?AWSAccessKeyId=123&Expires=456&Signature=abcdef
	 */
	function install( $args, $assoc_args ) {

		if ( ! is_dir( WP_PLUGIN_DIR ) ) {
			wp_mkdir_p( WP_PLUGIN_DIR );
		}

		parent::install( $args, $assoc_args );
	}

	/**
	 * Get a plugin.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : The plugin to get.
	 *
	 * [--field=<field>]
	 * : Instead of returning the whole plugin, returns the value of a single field.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields. Defaults to all fields.
	 *
	 * [--format=<format>]
	 * : Output list as table, json, CSV. Defaults to table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp plugin get bbpress --format=json
	 */
	public function get( $args, $assoc_args ) {
		$plugin = $this->fetcher->get_check( $args[0] );
		$file = $plugin->file;

		$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );

		$plugin_obj = (object)array(
			'name'        => Utils\get_plugin_name( $file ),
			'title'       => $plugin_data['Name'],
			'author'      => $plugin_data['Author'],
			'version'     => $plugin_data['Version'],
			'description' => wordwrap( $plugin_data['Description'] ),
			'status'      => $this->get_status( $file ),
		);

		if ( empty( $assoc_args['fields'] ) ) {
			$plugin_array = get_object_vars( $plugin_obj );
			$assoc_args['fields'] = array_keys( $plugin_array );
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_item( $plugin_obj );
	}

	/**
	 * Uninstall a plugin.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>...
	 * : One or more plugins to uninstall.
	 *
	 * [--deactivate]
	 * : Deactivate the plugin before uninstalling. Default behavior is to warn and skip if the plugin is active.
	 *
	 * [--skip-delete]
	 * : If set, the plugin files will not be deleted. Only the uninstall procedure
	 * will be run.
	 *
	 * ## EXAMPLES
	 *
	 *     wp plugin uninstall hello
	 */
	function uninstall( $args, $assoc_args = array() ) {
		foreach ( $this->fetcher->get_many( $args ) as $plugin ) {
			if ( is_plugin_active( $plugin->file ) && ! WP_CLI\Utils\get_flag_value( $assoc_args, 'deactivate' ) ) {
				WP_CLI::warning( "The '{$plugin->name}' plugin is active." );
				continue;
			}

			if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'deactivate' ) ) {
				WP_CLI::log( "Deactivating '{$plugin->name}'..." );
				$this->deactivate( array( $plugin->name ) );
			}

			uninstall_plugin( $plugin->file );

			if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-delete' ) && $this->_delete( $plugin ) ) {
				WP_CLI::success( "Uninstalled and deleted '$plugin->name' plugin." );
			} else {
				WP_CLI::success( "Ran uninstall procedure for '$plugin->name' plugin without deleting." );
			}
		}
	}

	/**
	 * Check if the plugin is installed.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : The plugin to check.
	 *
	 * ## EXAMPLES
	 *
	 *     wp plugin is-installed hello
	 *
	 * @subcommand is-installed
	 */
	function is_installed( $args, $assoc_args = array() ) {
		if ( $this->fetcher->get( $args[0] ) ) {
			exit( 0 );
		} else {
			exit( 1 );
		}
	}

	/**
	 * Delete plugin files.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>...
	 * : One or more plugins to delete.
	 *
	 * ## EXAMPLES
	 *
	 *     wp plugin delete hello
	 */
	function delete( $args, $assoc_args = array() ) {
		foreach ( $this->fetcher->get_many( $args ) as $plugin ) {
			if ( $this->_delete( $plugin ) ) {
				WP_CLI::success( "Deleted '{$plugin->name}' plugin." );
			}
		}
	}

	/**
	 * Get a list of plugins.
	 *
	 * ## OPTIONS
	 *
	 * [--<field>=<value>]
	 * : Filter results based on the value of a field.
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each plugin.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count. Default: table
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each plugin:
	 *
	 * * name
	 * * status
	 * * update
	 * * version
	 *
	 * These fields are optionally available:
	 *
	 * * update_version
	 * * update_package
	 * * update_id
	 * * title
	 * * description
	 *
	 * ## EXAMPLES
	 *
	 *     wp plugin list --status=active --format=json
	 *
	 * @subcommand list
	 */
	public function list_( $_, $assoc_args ) {
		parent::_list( $_, $assoc_args );
	}

	/* PRIVATES */

	private function check_active( $file, $network_wide ) {
		$required = $network_wide ? 'active-network' : 'active';

		return $required == $this->get_status( $file );
	}

	private function active_output( $name, $file, $network_wide, $action ) {
		$network_wide = $network_wide || ( is_multisite() && is_network_only_plugin( $file ) );

		$check = $this->check_active( $file, $network_wide );

		if ( ( $action == "activate" ) ? $check : ! $check ) {
			if ( $network_wide )
				WP_CLI::success( "Plugin '{$name}' network {$action}d." );
			else
				WP_CLI::success( "Plugin '{$name}' {$action}d." );
		} else {
			WP_CLI::warning( "Could not {$action} the '{$name}' plugin." );
		}
	}

	protected function get_status( $file ) {
		if ( is_plugin_active_for_network( $file ) )
			return 'active-network';

		if ( is_plugin_active( $file ) )
			return 'active';

		return 'inactive';
	}

	/**
	 * Get the details of a plugin.
	 *
	 * @param object
	 * @return array
	 */
	private function get_details( $file ) {
		$plugin_folder = get_plugins(  '/' . plugin_basename( dirname( $file ) ) );
		$plugin_file = basename( $file );

		return $plugin_folder[$plugin_file];
	}

	private function _delete( $plugin ) {
		$plugin_dir = dirname( $plugin->file );
		if ( '.' == $plugin_dir )
			$plugin_dir = $plugin->file;

		$path = path_join( WP_PLUGIN_DIR, $plugin_dir );

		if ( \WP_CLI\Utils\is_windows() ) {
			// Handles plugins that are not in own folders 
			// e.g. Hello Dolly -> plugins/hello.php
			if ( is_file( $path ) ) {			 
				$command = 'del /f /q ';
			} else {
				$command = 'rd /s /q ';			
			}
			$path = str_replace( "/", "\\", $path );
		} else {
			$command = 'rm -rf ';
		}

		return ! WP_CLI::launch( $command . $path );
	}
}

WP_CLI::add_command( 'plugin', 'Plugin_Command' );
