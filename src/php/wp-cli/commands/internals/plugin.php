<?php

WP_CLI::add_command('plugin', 'Plugin_Command');

/**
 * Implement plugin command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Plugin_Command extends WP_CLI_Command_With_Upgrade {

	protected $item_type = 'plugin';
	protected $upgrader = 'Plugin_Upgrader';
	protected $upgrade_refresh = 'wp_update_plugins';
	protected $upgrade_transient = 'update_plugins';

	private $mu_plugins;

	function __construct( $args, $assoc_args ) {
		require_once ABSPATH.'wp-admin/includes/plugin.php';
		require_once ABSPATH.'wp-admin/includes/plugin-install.php';

		parent::__construct( $args, $assoc_args );
	}

	protected function _status_single( $details, $name, $version, $status ) {
		WP_CLI::line( 'Plugin %9' . $name . '%n details:' );
		WP_CLI::line( '    Name: ' . $details[ 'Name' ] );
		WP_CLI::line( '    Status: ' . $status .'%n' );
		WP_CLI::line( '    Version: ' . $version );
		WP_CLI::line( '    Author: ' . $details[ 'Author' ] );
		WP_CLI::line( '    Description: ' . $details[ 'Description' ] );
	}

	// Show details about all plugins
	protected function status_all() {
		$this->mu_plugins = get_mu_plugins();

		$plugins = get_plugins();

		$plugins = array_merge( $plugins, $this->mu_plugins );

		// Print the header
		WP_CLI::line('Installed plugins:');

		foreach ($plugins as $file => $plugin) {
			if ( false === strpos( $file, '/' ) )
				$name = str_replace('.php', '', basename($file));
			else
				$name = dirname($file);

			if ( $this->get_update_status( $file ) ) {
				$line = ' %yU%n';
			} else {
				$line = '  ';
			}

			$status = $this->get_status( $file );

			$line .= $this->format_status( $status, 'short' ) . " $name%n";

			WP_CLI::line( $line );
		}

		// Print the footer
		WP_CLI::line();

		$legend = array(
			'I' => 'Inactive',
			'%gA' => 'Active',
			'%cM' => 'Must Use',
		);

		if ( is_multisite() )
			$legend['%bN'] = 'Network Active';

		self::legend( $legend );
	}

	/**
	 * Activate a plugin
	 *
	 * @param array $args
	 */
	function activate( $args, $assoc_args = array() ) {
		list( $file, $name ) = $this->parse_name( $args, __FUNCTION__ );

		$network_wide = isset( $assoc_args['network'] );

		activate_plugin( $file, '', $network_wide );

		if ( $this->check_active( $file, $network_wide ) ) {
			WP_CLI::success( "Plugin '$name' activated." );
		} else {
			WP_CLI::error( 'Could not activate plugin: ' . $name );
		}
	}

	/**
	 * Deactivate a plugin
	 *
	 * @param array $args
	 */
	function deactivate( $args, $assoc_args = array() ) {
		list( $file, $name ) = $this->parse_name( $args, __FUNCTION__ );

		$network_wide = isset( $assoc_args['network'] );

		deactivate_plugins( $file, false, $network_wide );

		if ( ! $this->check_active( $file, $network_wide ) ) {
			WP_CLI::success( "Plugin '$name' deactivated." );
		} else {
			WP_CLI::error( 'Could not deactivate plugin: ' . $name );
		}
	}

	private function check_active( $file, $network_wide ) {
		if ( $network_wide ) {
			$check = is_plugin_active_for_network( $file );
		} else {
			$check = is_plugin_active( $file );
		}

		return $check;
	}

	/**
	 * Toggle a plugin's activation state
	 *
	 * @param array $args
	 */
	function toggle( $args, $assoc_args = array() ) {
		list( $file, $name ) = $this->parse_name( $args, __FUNCTION__ );

		$network_wide = isset( $assoc_args['network'] );

		if ( $this->check_active( $file, $network_wide ) ) {
			$this->deactivate( $args, $assoc_args );
		} else {
			$this->activate( $args, $assoc_args );
		}
	}

	/**
	 * Get a plugin path
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	function path( $args, $assoc_args ) {
		$path = untrailingslashit( WP_PLUGIN_DIR );

		if ( !empty( $args ) ) {
			list( $file, $name ) = $this->parse_name( $args, __FUNCTION__ );
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
			$upgrader = WP_CLI::get_upgrader( $this->upgrader );
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
	 * Update a plugin (to the latest dev version)
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	function update( $args, $assoc_args ) {
		if ( isset( $assoc_args['version'] ) && 'dev' == $assoc_args['version'] ) {
			$this->delete( $args, array(), false );
			$this->install( $args, $assoc_args );
		} else {
			parent::update( $args, $assoc_args );
		}
	}

	protected function get_item_list() {
		return array_keys( get_plugins() );
	}

	/**
	 * Uninstall a plugin
	 *
	 * @param array $args
	 */
	function uninstall( $args, $assoc_args = array() ) {
		list( $file, $name ) = $this->parse_name( $args, __FUNCTION__ );

		if ( is_plugin_active( $file ) ) {
			WP_CLI::error( 'The plugin is active.' );
		}

		uninstall_plugin( $file );

		if ( !isset( $assoc_args['no-delete'] ) )
			$this->delete( $args );
	}

	/**
	 * Delete plugin files
	 *
	 * @param array $args
	 */
	function delete( $args, $assoc_args = array(), $exit_on_error = true ) {
		list( $file, $name ) = $this->parse_name( $args, __FUNCTION__ );

		$plugin_dir = dirname( $file );
		if ( '.' == $plugin_dir )
			$plugin_dir = $file;

		$command = 'rm -rf ' . path_join( WP_PLUGIN_DIR, $plugin_dir );

		return WP_CLI::launch( $command, $exit_on_error );
	}

	/* PRIVATES */

	protected function get_status( $file ) {
		if ( isset( $this->mu_plugins[ $file ] ) )
			return 'must-use';

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
	 * @param string $subcommand
	 * @param bool $exit
	 * @return array
	 */
	protected function parse_name( $args, $subcommand ) {
		if ( empty( $args ) ) {
			WP_CLI::line( "usage: wp plugin $subcommand <plugin-name>" );
			exit;
		}

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
}
