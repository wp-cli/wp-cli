<?php

/**
 * Manage plugins.
 *
 * @package wp-cli
 */
class Plugin_Command extends \WP_CLI\CommandWithUpgrade {

	protected $item_type = 'plugin';
	protected $upgrader = 'Plugin_Upgrader';
	protected $upgrade_refresh = 'wp_update_plugins';
	protected $upgrade_transient = 'update_plugins';

	function __construct() {
		require_once ABSPATH.'wp-admin/includes/plugin.php';
		require_once ABSPATH.'wp-admin/includes/plugin-install.php';

		parent::__construct();
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
		list( $file, $name ) = $this->parse_name( $args );

		$details = $this->get_details( $file );

		$status = $this->format_status( $this->get_status( $file ), 'long' );

		$version = $details[ 'Version' ];

		if ( $this->has_update( $file ) )
			$version .= ' (%gUpdate available%n)';

		$this->_status_single( $details, $name, $version, $status );
	}

	protected function _status_single( $details, $name, $version, $status ) {
		WP_CLI::line( 'Plugin %9' . $name . '%n details:' );
		WP_CLI::line( '    Name: ' . $details[ 'Name' ] );
		WP_CLI::line( '    Status: ' . $status .'%n' );
		WP_CLI::line( '    Version: ' . $version );
		WP_CLI::line( '    Author: ' . $details[ 'Author' ] );
		WP_CLI::line( '    Description: ' . $details[ 'Description' ] );
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
		list( $file, $name ) = $this->parse_name( $args );

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
		list( $file, $name ) = $this->parse_name( $args );

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
		list( $file, $name ) = $this->parse_name( $args );

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
			list( $file, $name ) = $this->parse_name( $args );
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
			list( $link ) = explode( $slug, $api->download_link );

			if ( 'dev' == $assoc_args['version'] ) {
				$api->download_link = $link . $slug . '.zip';
				$api->version = 'Development Version';
			} else {
				$api->download_link = $link . $slug . '.' . $assoc_args['version'] .'.zip';
				$api->version = $assoc_args['version'];

				// check if the requested version exists
				$response = wp_remote_head( $api->download_link );
				if ( !$response || $response['headers']['content-type'] != 'application/octet-stream' ) {
					WP_CLI::error( "Can't find the requested plugin's version " . $assoc_args['version'] . " in the WordPress.org plugins repository." );
				}
			}
		}

		$status = install_plugin_install_status( $api );

		WP_CLI::line( sprintf( 'Installing %s (%s)', $api->name, $api->version ) );

		switch ( $status['status'] ) {
		case 'update_available':
		case 'install':
			$upgrader = WP_CLI\Utils\get_upgrader( $this->upgrader );
			$result = $upgrader->install( $api->download_link );

			if ( $result && isset( $assoc_args['activate'] ) ) {
				WP_CLI::line( "Activating '$slug'..." );
				$this->activate( array( $slug ) );
			}

			break;
		case 'newer_installed':
			WP_CLI::error( sprintf( 'Newer version (%s) installed.', $status['version'] ) );
			break;
		case 'latest_installed':
			WP_CLI::error( 'Latest version already installed.' );
			break;
		}
	}

	/**
	 * Update a plugin.
	 *
	 * @synopsis <plugin> [--version=<version>]
	 */
	function update( $args, $assoc_args ) {
		if ( isset( $assoc_args['version'] ) && 'dev' == $assoc_args['version'] ) {
			$this->delete( $args, array(), false );
			$this->install( $args, $assoc_args );
		} else {
			list( $basename ) = $this->parse_name( $args );

			$was_active = is_plugin_active( $basename );
			$was_network_active = is_plugin_active_for_network( $basename );

			parent::_update( $basename );

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
	 * @synopsis <plugin|zip> [--version=<version>] [--activate]
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
		list( $file, $name ) = $this->parse_name( $args );

		if ( is_plugin_active( $file ) ) {
			WP_CLI::error( 'The plugin is active.' );
		}

		uninstall_plugin( $file );

		if ( !isset( $assoc_args['no-delete'] ) )
			$this->delete( $args );
	}

	/**
	 * Delete plugin files.
	 *
	 * @synopsis <plugin>
	 */
	function delete( $args, $assoc_args = array(), $exit_on_error = true ) {
		list( $file, $name ) = $this->parse_name( $args );

		$plugin_dir = dirname( $file );
		if ( '.' == $plugin_dir )
			$plugin_dir = $file;

		$command = 'rm -rf ' . path_join( WP_PLUGIN_DIR, $plugin_dir );

		return WP_CLI::launch( $command, $exit_on_error );
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
	 * Parse the name of a plugin to a filename, check if it exists
	 *
	 * @param array $args
	 * @return array
	 */
	protected function parse_name( $args ) {
		$name = $args[0];

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

		return array( $file, $name );
	}

	private function get_name( $file ) {
		if ( false === strpos( $file, '/' ) )
			$name = basename( $file, '.php' );
		else
			$name = dirname( $file );

		return $name;
	}
}

WP_CLI::add_command( 'plugin', 'Plugin_Command' );

