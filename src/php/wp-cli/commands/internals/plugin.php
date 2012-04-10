<?php

WP_CLI::addCommand('plugin', 'PluginCommand');

/**
 * Implement plugin command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class PluginCommand extends WP_CLI_Command_With_Upgrade {

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

	/**
	 * Get the status of one or all plugins
	 *
	 * @param array $args
	 */
	function status( $args = array(), $vars = array() ) {
		$this->mu_plugins = get_mu_plugins();

		if ( empty( $args ) ) {
			$this->list_plugins();
			return;
		}

		list( $file, $name ) = $this->parse_name( $args, __FUNCTION__ );

		$details = $this->get_details( $file );

		$status = $this->get_status( $file, true );

		$version = $details[ 'Version' ];

		if ( $this->get_update_status( $file ) )
			$version .= ' (%gUpdate available%n)';

		WP_CLI::line( 'Plugin %9' . $name . '%n details:' );
		WP_CLI::line( '    Name: ' . $details[ 'Name' ] );
		WP_CLI::line( '    Status: ' . $status .'%n' );
		WP_CLI::line( '    Version: ' . $version );
		WP_CLI::line( '    Author: ' . $details[ 'Author' ] );
		WP_CLI::line( '    Description: ' . $details[ 'Description' ] );
	}

	private function list_plugins() {
		// Force WordPress to update the plugin list
		wp_update_plugins();

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

			$line .= $this->get_status( $file ) . " $name%n";

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

		WP_CLI::legend( $legend );
	}

	private function get_status( $file, $long = false ) {
		if ( isset( $this->mu_plugins[ $file ] ) ) {
			$line  = '%c';
			$line .= $long ? 'Must Use' : 'M';
		} elseif ( is_plugin_active_for_network( $file ) ) {
			$line  = '%b';
			$line .= $long ? 'Network Active' : 'N';
		} elseif ( is_plugin_active( $file ) ) {
			$line  = '%g';
			$line .= $long ? 'Active' : 'A';
		} else {
			$line  = $long ? 'Inactive' : 'I';
		}

		return $line;
	}

	/**
	 * Activate a plugin
	 *
	 * @param array $args
	 */
	function activate( $args ) {
		list( $file, $name ) = $this->parse_name( $args, __FUNCTION__ );

		activate_plugin( $file );

		if ( !is_plugin_active( $file ) ) {
			WP_CLI::error( 'Could not activate this plugin: ' . $name );
		} else {
			WP_CLI::success( "Plugin '$name' activated." );
		}
	}

	/**
	 * Deactivate a plugin
	 *
	 * @param array $args
	 */
	function deactivate( $args ) {
		list( $file, $name ) = $this->parse_name( $args, __FUNCTION__ );

		deactivate_plugins( $file );

		if ( !is_plugin_inactive( $file ) ) {
			WP_CLI::error( 'Could not deactivate this plugin: '.$name );
		} else {
			WP_CLI::success( "Plugin '$name' deactivated." );
		}
	}

	/**
	 * Toggle a plugin's activation state
	 *
	 * @param array $args
	 */
	function toggle( $args ) {
		list( $file, $name ) = $this->parse_name( $args, __FUNCTION__ );

		if ( is_plugin_active( $file ) ) {
			$this->deactivate( $args );
		} else {
			$this->activate( $args );
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

	/**
	 * Install a new plugin
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	function install( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::line( "usage: wp plugin install <plugin-name>" );
			exit;
		}

		$slug = stripslashes( $args[0] );

		// Force WordPress to update the plugin list
		wp_update_plugins();

		$api = plugins_api( 'plugin_information', array( 'slug' => $slug ) );
		if ( !$api ) {
			WP_CLI::error( 'Can\'t find the plugin in the WordPress.org plugins repository.' );
			exit();
		}

		if ( isset( $assoc_args['dev'] ) ) {
			list( $link ) = explode( $slug, $api->download_link );

			$api->download_link = $link . $slug . '.zip';
			$api->version = 'Development Version';
		} else if ( isset( $assoc_args['version'] ) ) {
			list( $link ) = explode( $slug, $api->download_link );

			$api->download_link = $link . $slug . '.' . $assoc_args['version'] .'.zip';
			$api->version = $assoc_args['version'];
			
			//check if the requested version exists
			$version_check_response = wp_remote_head($api->download_link);
			if (!$version_check_response || $version_check_response['headers']['content-type'] != 'application/octet-stream') {
				WP_CLI::error( 'Can\'t find the requested plugin\'s version ' . $assoc_args['version'] . ' in the WordPress.org plugins repository.');
				exit();
			}
		}

		$status = install_plugin_install_status( $api );

		WP_CLI::line( sprintf( 'Installing %s (%s)', $api->name, $api->version ) );

		switch ( $status['status'] ) {
		case 'update_available':
		case 'install':
			$upgrader = WP_CLI::get_upgrader( 'Plugin_Upgrader' );
			$result = $upgrader->install( $api->download_link );

			if ( $result ) {
				if ( isset( $assoc_args['activate'] ) ) {
					system( "wp plugin activate " . WP_CLI::compose_args( $args, $assoc_args ) );
				}
			}

			break;
		case 'newer_installed':
			WP_CLI::error( sprintf( 'Newer version (%s) installed', $status['version'] ) );
			break;
		case 'latest_installed':
			WP_CLI::error( 'Latest version already installed' );
			break;
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
	function uninstall( $args ) {
		list( $file, $name ) = $this->parse_name( $args, __FUNCTION__ );

		if ( is_plugin_active( $file ) ) {
			WP_CLI::error( 'The plugin is active.' );
		}

		uninstall_plugin( $file );
	}

	/**
	 * Delete a plugin
	 *
	 * @param array $args
	 */
	function delete( $args ) {
		list( $file, $name ) = $this->parse_name( $args, __FUNCTION__ );

		if ( is_plugin_active( $file ) ) {
			WP_CLI::error( 'The plugin is active.' );
		}

		if ( !delete_plugins( array( $file ) ) ) {
			WP_CLI::error( 'There was an error while deleting the plugin.' );
		}
	}

	/* PRIVATES */

	/**
	 * Get the details of a plugin
	 *
	 * @param string $file
	 * @return array
	 */
	private function get_details( $file ) {
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
		}

		$plugins = get_plugins();

		if ( !isset( $plugins[$file] ) ) {
			WP_CLI::error( "The plugin '$name' could not be found." );
			exit();
		}

		return array( $file, $name );
	}

	/**
	 * Help function for this command
	 */
	public static function help() {
		WP_CLI::line( <<<EOB
usage: wp plugin <sub-command> [<plugin-name>]
   or: wp plugin path [<plugin-name>] [--dir]
   or: wp plugin install <plugin-name> [--activate] [--dev]

Available sub-commands:
   status       display status of all installed plugins or of a particular plugin

   activate     activate a particular plugin

   deactivate   deactivate a particular plugin

   toggle       toggle activation state of a particular plugin

   path         print path to the plugin's file
      --dir        get the path to the closest parent directory

   install      install a plugin from wordpress.org
      --activate   activate the plugin after installing it
      --dev        install the development version

   update       update a plugin from wordpress.org
      --all        update all plugins from wordpress.org

   uninstall    run the uninstallation procedure for a plugin

   delete       delete a plugin
EOB
	);
	}
}
