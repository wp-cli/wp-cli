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
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : A particular plugin to show the status for.
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
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : The plugin to activate.
	 *
	 * --network
	 * : If set, the plugin will be activated for the entire multisite network.
	 *
	 * @synopsis <plugin> [--network]
	 */
	function activate( $args, $assoc_args = array() ) {
		$name = $args[0];
		$file = $this->parse_name( $name );

		$network_wide = isset( $assoc_args['network'] );

		activate_plugin( $file, '', $network_wide );

		if ( $this->check_active( $file, $network_wide ) ) {
			if ( $network_wide )
				WP_CLI::success( "Plugin '$name' network activated." );
			else
				WP_CLI::success( "Plugin '$name' activated." );
		} else {
			WP_CLI::error( 'Could not activate plugin: ' . $name );
		}
	}

	/**
	 * Deactivate a plugin.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : The plugin to deactivate.
	 *
	 * --network
	 * : If set, the plugin will be deactivated for the entire multisite network.
	 *
	 * @synopsis <plugin> [--network]
	 */
	function deactivate( $args, $assoc_args = array() ) {
		$name = $args[0];
		$file = $this->parse_name( $name );

		$network_wide = isset( $assoc_args['network'] );

		deactivate_plugins( $file, false, $network_wide );

		if ( ! $this->check_active( $file, $network_wide ) ) {
			if ( $network_wide )
				WP_CLI::success( "Plugin '$name' network deactivated." );
			else
				WP_CLI::success( "Plugin '$name' deactivated." );
		} else {
			WP_CLI::error( 'Could not deactivate plugin: ' . $name );
		}
	}

	/**
	 * Toggle a plugin's activation state.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : The plugin to toggle.
	 *
	 * --network
	 * : If set, the plugin will be toggled for the entire multisite network.
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
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : The plugin to get the path to. If not set, will return the path to the
	 * plugins directory.
	 *
	 * --dir
	 * : If set, get the path to the closest parent directory, instead of the
	 * plugin file.
	 *
	 * ## EXAMPLES
	 *
	 *     cd $(wp theme path)
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
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : The plugin to update.
	 *
	 * --version=dev
	 * : If set, the plugin will be updated to the latest development version,
	 * regardless of what version is currently installed.
	 *
	 * ## EXAMPLES
	 *
	 *     wp plugin update bbpress --version=dev
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
	 * ## OPTIONS
	 *
	 * --dry-run
	 * : Pretend to do the updates, to see what would happen.
	 *
	 * ## EXAMPLES
	 *
	 *     wp plugin update-all
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
	 * ## OPTIONS
	 *
	 * <plugin|zip|url>
	 * : A plugin slug, the path to a local zip file, or URL to a remote zip file.
	 *
	 * --version=<version>
	 * : If set, get that particular version from wordpress.org, instead of the
	 * stable version.
	 *
	 * --force
	 * : If set, the command will overwrite any installed version of the plugin, without prompting
	 * for confirmation.
	 *
	 * --activate
	 * : If set, the plugin will be activated immediately after install.
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
	 *
	 * @synopsis <plugin|zip|url> [--version=<version>] [--force] [--activate]
	 */
	function install( $args, $assoc_args ) {
		parent::install( $args, $assoc_args );
	}

	/**
	 * Uninstall a plugin.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : The plugin to uninstall.
	 *
	 * --no-delete
	 * : If set, the plugin files will not be deleted. Only the uninstall procedure
	 * will be run.
	 *
	 * ## EXAMPLES
	 *
	 *     wp plugin uninstall hello
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
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : The plugin to delete.
	 *
	 * ## EXAMPLES
	 *
	 *     wp plugin delete hello
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
	 * ## OPTIONS
	 *
	 * * `--format`=<format>:
	 *
	 *     Output list as table, CSV or JSON. Defaults to table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp plugin list --format=json
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

