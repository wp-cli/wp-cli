<?php

/**
 * Manage plugins.
 *
 * @package wp-cli
 */
class Plugin_Command extends \WP_CLI\CommandWithUpgrade {

	protected $item_type = 'plugin';
	protected $upgrade_refresh = 'wp_update_plugins';
	protected $upgrade_transient = 'update_plugins';

	protected $fields = array(
		'name',
		'status',
		'update',
		'version'
	);

	function __construct() {
		require_once ABSPATH.'wp-admin/includes/plugin.php';
		require_once ABSPATH.'wp-admin/includes/plugin-install.php';

		parent::__construct();
	}

	protected function get_upgrader_class( $force ) {
		return $force ? '\\WP_CLI\\DestructivePluginUpgrader' : 'Plugin_Upgrader';
	}

	/**
	 * See the status of one or all plugins.
	 *
	 * @synopsis [<plugin>]
	 */
	function status( $args ) {
		parent::status( $args );
	}

	protected function status_single( $args ) {
		$name = $args[0];
		$file = $this->parse_name( $name );

		$details = $this->get_details( $file );

		$status = $this->format_status( $this->get_status( $file ), 'long' );

		$version = $details[ 'Version' ];

		if ( $this->has_update( $file ) )
			$version .= ' (%gUpdate available%n)';

		echo WP_CLI::colorize( \WP_CLI\Utils\mustache_render( 'plugin-status.mustache', array(
			'slug' => $name,
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
			$items[ $file ] = array(
				'name' => $this->get_name( $file ),
				'status' => 'must-use',
				'update' => false
			);
		}

		return $items;
	}

	/**
	 * Activate a plugin.
	 *
	 * @synopsis <plugin> [--network]
	 */
	function activate( $args, $assoc_args = array() ) {
		$name = $args[0];
		$file = $this->parse_name( $name );

		$network_wide = isset( $assoc_args['network'] );

		activate_plugin( $file, '', $network_wide );

		if ( $this->check_active( $file, $network_wide ) ) {
			WP_CLI::success( "Plugin '$name' activated." );
		} else {
			WP_CLI::error( 'Could not activate plugin: ' . $name );
		}
	}

	/**
	 * Deactivate a plugin.
	 *
	 * @synopsis <plugin> [--network]
	 */
	function deactivate( $args, $assoc_args = array() ) {
		$name = $args[0];
		$file = $this->parse_name( $name );

		$network_wide = isset( $assoc_args['network'] );

		deactivate_plugins( $file, false, $network_wide );

		if ( ! $this->check_active( $file, $network_wide ) ) {
			WP_CLI::success( "Plugin '$name' deactivated." );
		} else {
			WP_CLI::error( 'Could not deactivate plugin: ' . $name );
		}
	}

	/**
	 * Toggle a plugin's activation state.
	 *
	 * @synopsis <plugin> [--network]
	 */
	function toggle( $args, $assoc_args = array() ) {
		$name = $args[0];
		$file = $this->parse_name( $name );

		$network_wide = isset( $assoc_args['network'] );

		if ( $this->check_active( $file, $network_wide ) ) {
			$this->deactivate( $args, $assoc_args );
		} else {
			$this->activate( $args, $assoc_args );
		}
	}

	/**
	 * Get the path to a plugin or to the plugin directory.
	 *
	 * @synopsis [<plugin>] [--dir]
	 */
	function path( $args, $assoc_args ) {
		$path = untrailingslashit( WP_PLUGIN_DIR );

		if ( !empty( $args ) ) {
			$file = $this->parse_name( $args[0] );
			$path .= '/' . $file;

			if ( isset( $assoc_args['dir'] ) )
				$path = dirname( $path );
		}

		WP_CLI::line( $path );
	}

	protected function install_from_repo( $slug, $assoc_args ) {
		$api = plugins_api( 'plugin_information', array( 'slug' => $slug ) );

		if ( is_wp_error( $api ) ) {
			if ( null === maybe_unserialize( $api->get_error_data() ) )
				WP_CLI::error( "Can't find the plugin in the WordPress.org repository." );
			else
				WP_CLI::error( $api );
		}

		if ( isset( $assoc_args['version'] ) ) {
			self::alter_api_response( $api, $assoc_args['version'] );
		}

		$status = install_plugin_install_status( $api );

		if ( !isset( $assoc_args['force'] ) && 'install' != $status['status'] ) {
			// We know this will fail, so avoid a needless download of the package.
			WP_CLI::error( 'Plugin already installed.' );
		}

		WP_CLI::log( sprintf( 'Installing %s (%s)', $api->name, $api->version ) );
		$result = $this->get_upgrader( $assoc_args )->install( $api->download_link );

		if ( $result && isset( $assoc_args['activate'] ) ) {
			WP_CLI::log( "Activating '$slug'..." );
			$this->activate( array( $slug ) );
		}
	}

	/**
	 * Update a plugin.
	 *
	 * @synopsis <plugin> [--version=<version>]
	 */
	function update( $args, $assoc_args ) {
		$name = $args[0];
		$basename = $this->parse_name( $name );

		if ( isset( $assoc_args['version'] ) && 'dev' == $assoc_args['version'] ) {
			$this->_delete( $basename, false );
			$this->install( $args, $assoc_args );
		} else {
			$was_active = is_plugin_active( $basename );
			$was_network_active = is_plugin_active_for_network( $basename );

			call_user_func( $this->upgrade_refresh );

			$this->get_upgrader( $assoc_args )->upgrade( $basename );

			if ( $was_active ) {
				$new_args = array( $args[0] );

				$new_assoc_args = array();
				if ( $was_network_active )
					$new_assoc_args['network'] = true;

				$this->activate( $new_args, $new_assoc_args );
			}
		}
	}

	/**
	 * Update all plugins.
	 *
	 * @subcommand update-all
	 * @synopsis [--dry-run]
	 */
	function update_all( $args, $assoc_args ) {
		parent::update_all( $args, $assoc_args );
	}

	protected function get_item_list() {
		$items = array();

		foreach ( get_plugins() as $file => $details ) {
			$items[ $file ] = array(
				'name' => $this->get_name( $file ),
				'status' => $this->get_status( $file ),
				'update' => $this->has_update( $file ),
				'version' => $details['Version'],
				'update_id' => $file,
			);
		}

		return $items;
	}

	/**
	 * Install a plugin.
	 *
	 * @synopsis <plugin|zip|url> [--version=<version>] [--force] [--activate]
	 */
	function install( $args, $assoc_args ) {
		parent::install( $args, $assoc_args );
	}

	/**
	 * Uninstall a plugin.
	 *
	 * @synopsis <plugin> [--no-delete]
	 */
	function uninstall( $args, $assoc_args = array() ) {
		$name = $args[0];
		$file = $this->parse_name( $name );

		if ( is_plugin_active( $file ) ) {
			WP_CLI::error( 'The plugin is active.' );
		}

		uninstall_plugin( $file );

		if ( isset( $assoc_args['no-delete'] ) )
			return;

		if ( $this->_delete( $file ) ) {
			WP_CLI::success( sprintf( "Uninstalled '%s' plugin.", $name ) );
		}
	}

	/**
	 * Delete plugin files.
	 *
	 * @synopsis <plugin>
	 */
	function delete( $args, $assoc_args = array() ) {
		$name = $args[0];
		$file = $this->parse_name( $name );

		if ( $this->_delete( $file ) ) {
			WP_CLI::success( sprintf( "Deleted '%s' plugin.", $name ) );
		}
	}

	/**
	 * Get a list of plugins.
	 *
	 * @subcommand list
	 * @synopsis [--format=<format>]
	 */
	function _list( $_, $assoc_args ) {
		parent::_list( $_, $assoc_args );
	}

	/* PRIVATES */

	private function check_active( $file, $network_wide ) {
		$required = $network_wide ? 'active-network' : 'active';

		return $required == $this->get_status( $file );
	}

	protected function get_status( $file ) {
		if ( is_plugin_active_for_network( $file ) )
			return 'active-network';

		if ( is_plugin_active( $file ) )
			return 'active';

		return 'inactive';
	}

	/**
	 * Get the details of a plugin
	 *
	 * @param string $file
	 * @return array
	 */
	protected function get_details( $file ) {
		$plugin_folder = get_plugins(  '/' . plugin_basename( dirname( $file ) ) );
		$plugin_file = basename( ( $file ) );

		return $plugin_folder[$plugin_file];
	}

	/**
	 * Parse the name of a plugin to a filename; check if it exists.
	 *
	 * @param string name
	 * @return string
	 */
	private function parse_name( $name ) {
		$plugins = get_plugins( '/' . $name );

		if ( !empty( $plugins ) ) {
			$file = $name . '/' . key( $plugins );
		}
		else {
			$file = $name . '.php';

			$plugins = get_plugins();

			if ( !isset( $plugins[$file] ) ) {
				WP_CLI::error( "The plugin '$name' could not be found." );
				exit();
			}
		}

		return $file;
	}

	/**
	 * Converts a plugin basename back into a friendly slug.
	 */
	private function get_name( $file ) {
		if ( false === strpos( $file, '/' ) )
			$name = basename( $file, '.php' );
		else
			$name = dirname( $file );

		return $name;
	}

	private function _delete( $file ) {
		$plugin_dir = dirname( $file );
		if ( '.' == $plugin_dir )
			$plugin_dir = $file;

		$command = 'rm -rf ' . path_join( WP_PLUGIN_DIR, $plugin_dir );

		return ! WP_CLI::launch( $command );
	}
}

WP_CLI::add_command( 'plugin', 'Plugin_Command' );

