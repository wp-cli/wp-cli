<?php

use \WP_CLI\Utils;

/**
 * Manage plugins.
 *
 * ## EXAMPLES
 *
 *     # Activate plugin
 *     $ wp plugin activate hello-dolly
 *     Success: Plugin 'hello-dolly' activated.
 *
 *     # Deactivate plugin
 *     $ wp plugin deactivate hello-dolly
 *     Success: Plugin 'hello-dolly' deactivated.
 *
 *     # Delete plugin
 *     $ wp plugin delete hello-dolly
 *     Success: Deleted 'hello-dolly' plugin.
 *
 *     # Install the latest version from wordpress.org and activate
 *     $ wp plugin install bbpress --activate
 *     Installing bbPress (2.5.9)
 *     Downloading install package from https://downloads.wordpress.org/plugin/bbpress.2.5.9.zip...
 *     Using cached file '/home/vagrant/.wp-cli/cache/plugin/bbpress-2.5.9.zip'...
 *     Unpacking the package...
 *     Installing the plugin...
 *     Plugin installed successfully.
 *     Activating 'bbpress'...
 *     Success: Plugin 'bbpress' activated.
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
	 *
	 * ## EXAMPLES
	 *
	 *     # Displays status of all plugins
	 *     $ wp plugin status
	 *     5 installed plugins:
	 *       I akismet                3.1.11
	 *       I easy-digital-downloads 2.5.16
	 *       A theme-check            20160523.1
	 *       I wen-logo-slider        2.0.3
	 *       M ns-pack                1.0.0
	 *     Legend: I = Inactive, A = Active, M = Must Use
	 *
	 *     # Displays status of a plugin
	 *     $ wp plugin status theme-check
	 *     Plugin theme-check details:
	 *         Name: Theme Check
	 *         Status: Active
	 *         Version: 20160523.1
	 *         Author: Otto42, pross
	 *         Description: A simple and easy way to test your theme for all the latest WordPress standards and practices. A great theme development tool!
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
	 * [--page=<page>]
	 * : Optional page to display.
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--per-page=<per-page>]
	 * : Optional number of results to display.
	 * ---
	 * default: 10
	 * ---
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
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - count
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp plugin search dsgnwrks --per-page=20 --format=json
	 *     Success: Showing 3 of 3 plugins.
	 *     [{"name":"DsgnWrks Instagram Importer Debug","slug":"dsgnwrks-instagram-importer-debug","rating":0},{"name":"DsgnWrks Instagram Importer","slug":"dsgnwrks-instagram-importer","rating":84},{"name":"DsgnWrks Twitter Importer","slug":"dsgnwrks-twitter-importer","rating":80}]
	 *
	 *     $ wp plugin search dsgnwrks --fields=name,version,slug,rating,num_ratings
	 *     Success: Showing 3 of 3 plugins.
	 *     +-----------------------------------+---------+-----------------------------------+--------+-------------+
	 *     | name                              | version | slug                              | rating | num_ratings |
	 *     +-----------------------------------+---------+-----------------------------------+--------+-------------+
	 *     | DsgnWrks Instagram Importer Debug | 0.1.6   | dsgnwrks-instagram-importer-debug | 0      | 0           |
	 *     | DsgnWrks Instagram Importer       | 1.3.7   | dsgnwrks-instagram-importer       | 84     | 23          |
	 *     | DsgnWrks Twitter Importer         | 1.1.1   | dsgnwrks-twitter-importer         | 80     | 1           |
	 *     +-----------------------------------+---------+-----------------------------------+--------+-------------+
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
	 *
	 * ## EXAMPLES
	 *
	 *     # Activate plugin
	 *     $ wp plugin activate hello-dolly
	 *     Success: Plugin 'hello-dolly' activated.
	 *
	 *     # Activate plugin in entire multisite network
	 *     $ wp plugin activate hello-dolly --network
	 *     Success: Plugin 'hello-dolly' network activated.
	 */
	function activate( $args, $assoc_args = array() ) {
		$network_wide = \WP_CLI\Utils\get_flag_value( $assoc_args, 'network' );
		$all = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );

		if ( $all ) {
			$args = array_map( function( $file ){
				return Utils\get_plugin_name( $file );
			}, array_keys( get_plugins() ) );
		}

		$needing_activation = count( $args );
		foreach ( $this->fetcher->get_many( $args ) as $plugin ) {
			$status = $this->get_status( $plugin->file );
			if ( $all && in_array( $status, array( 'active', 'active-network' ) ) ) {
				$needing_activation--;
				continue;
			}
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

		if ( ! $needing_activation ) {
			WP_CLI::success( 'All plugins are already activated.' );
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
	 *
	 * ## EXAMPLES
	 *
	 *     # Deactivate plugin
	 *     $ wp plugin deactivate hello-dolly
	 *     Success: Plugin 'hello-dolly' deactivated.
	 */
	function deactivate( $args, $assoc_args = array() ) {
		$network_wide = \WP_CLI\Utils\get_flag_value( $assoc_args, 'network' );
		$disable_all = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' );

		if ( $disable_all ) {
			$args = array_map( function( $file ){
				return Utils\get_plugin_name( $file );
			}, array_keys( get_plugins() ) );
		}

		$needing_deactivation = count( $args );
		foreach ( $this->fetcher->get_many( $args ) as $plugin ) {

			$status = $this->get_status( $plugin->file );
			if ( $disable_all && ! in_array( $status, array( 'active', 'active-network' ) ) ) {
				$needing_deactivation--;
				continue;
			}

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

			if ( ! is_network_admin() ) {
				update_option( 'recently_activated',
					array( $plugin->file => time() ) + (array) get_option( 'recently_activated' ) );
			} else {
				update_site_option( 'recently_activated',
					array( $plugin->file => time() ) + (array) get_site_option( 'recently_activated' ) );
			}

			$this->active_output( $plugin->name, $plugin->file, $network_wide, "deactivate" );

			if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'uninstall' ) ) {
				WP_CLI::log( "Uninstalling '{$plugin->name}'..." );
				$this->uninstall( array( $plugin->name ) );
			}

		}

		if ( ! $needing_deactivation ) {
			WP_CLI::success( 'All plugins are already deactivated.' );
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
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp plugin toggle akismet
	 *     Success: Plugin 'akismet' deactivated.
	 *
	 *     $ wp plugin toggle akismet
	 *     Success: Plugin 'akismet' activated.
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
	 *     $ cd $(wp plugin path) && pwd
	 *     /var/www/wordpress/wp-content/plugins
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
	 * [--format=<format>]
	 * : Output summary as table or summary. Defaults to table.
	 *
	 * [--version=<version>]
	 * : If set, the plugin will be updated to the specified version.
	 *
	 * [--dry-run]
	 * : Preview which plugins would be updated.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp plugin update bbpress --version=dev
	 *     Installing bbPress (Development Version)
	 *     Downloading install package from https://downloads.wordpress.org/plugin/bbpress.zip...
	 *     Unpacking the package...
	 *     Installing the plugin...
	 *     Removing the old version of the plugin...
	 *     Plugin updated successfully.
	 *
	 *     $ wp plugin update --all
	 *     Enabling Maintenance mode...
	 *     Downloading update from https://downloads.wordpress.org/plugin/akismet.3.1.11.zip...
	 *     Unpacking the update...
	 *     Installing the latest version...
	 *     Removing the old version of the plugin...
	 *     Plugin updated successfully.
	 *     Downloading update from https://downloads.wordpress.org/plugin/nginx-champuru.3.2.0.zip...
	 *     Unpacking the update...
	 *     Installing the latest version...
	 *     Removing the old version of the plugin...
	 *     Plugin updated successfully.
	 *     Disabling Maintenance mode...
	 *     Success: Updated 2/2 plugins.
	 *     +------------------------+-------------+-------------+---------+
	 *     | name                   | old_version | new_version | status  |
	 *     +------------------------+-------------+-------------+---------+
	 *     | akismet                | 3.1.3       | 3.1.11      | Updated |
	 *     | nginx-cache-controller | 3.1.1       | 3.2.0       | Updated |
	 *     +------------------------+-------------+-------------+---------+
	 *
	 * @alias upgrade
	 */
	function update( $args, $assoc_args ) {
		if ( isset( $assoc_args['version'] ) ) {
			foreach ( $this->fetcher->get_many( $args ) as $plugin ) {
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
	 *     $ wp plugin install bbpress --activate
	 *     Installing bbPress (2.5.9)
	 *     Downloading install package from https://downloads.wordpress.org/plugin/bbpress.2.5.9.zip...
	 *     Using cached file '/home/vagrant/.wp-cli/cache/plugin/bbpress-2.5.9.zip'...
	 *     Unpacking the package...
	 *     Installing the plugin...
	 *     Plugin installed successfully.
	 *     Activating 'bbpress'...
	 *     Success: Plugin 'bbpress' activated.
	 *
	 *     # Install the development version from wordpress.org
	 *     $ wp plugin install bbpress --version=dev
	 *     Installing bbPress (Development Version)
	 *     Downloading install package from https://downloads.wordpress.org/plugin/bbpress.zip...
	 *     Unpacking the package...
	 *     Installing the plugin...
	 *     Plugin installed successfully.
	 *
	 *     # Install from a local zip file
	 *     $ wp plugin install ../my-plugin.zip
	 *     Unpacking the package...
	 *     Installing the plugin...
	 *     Plugin installed successfully.
	 *
	 *     # Install from a remote zip file
	 *     $ wp plugin install http://s3.amazonaws.com/bucketname/my-plugin.zip?AWSAccessKeyId=123&Expires=456&Signature=abcdef
	 *     Downloading install package from http://s3.amazonaws.com/bucketname/my-plugin.zip?AWSAccessKeyId=123&Expires=456&Signature=abcdef
	 *     Unpacking the package...
	 *     Installing the plugin...
	 *     Plugin installed successfully.
	 *
	 *     # Forcefully re-install all installed plugins
	 *     $ wp plugin install $(wp plugin list --field=name) --force
	 *     Installing Akismet (3.1.11)
	 *     Downloading install package from https://downloads.wordpress.org/plugin/akismet.3.1.11.zip...
	 *     Unpacking the package...
	 *     Installing the plugin...
	 *     Removing the old version of the plugin...
	 *     Plugin updated successfully
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
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp plugin get bbpress --format=json
	 *     {"name":"bbpress","title":"bbPress","author":"The bbPress Contributors","version":"2.6-alpha","description":"bbPress is forum software with a twist from the creators of WordPress.","status":"active"}
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
	 *     $ wp plugin uninstall hello
	 *     Success: Uninstalled and deleted 'hello' plugin.
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
	 * Returns exit code 0 when installed, 1 when uninstalled.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : The plugin to check.
	 *
	 * ## EXAMPLES
	 *
	 *     # Check whether plugin is installed; exit status 0 if installed, otherwise 1
	 *     $ wp plugin is-installed hello-dolly
	 *     $ echo $?
	 *     1
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
	 * Delete plugin files without deactivating or uninstalling.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>...
	 * : One or more plugins to delete.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete plugin
	 *     $ wp plugin delete hello
	 *     Success: Deleted 'hello' plugin.
	 *
	 *     # Delete inactive plugins
	 *     $ wp plugin delete $(wp plugin list --status=inactive --field=name)
	 *     Success: Deleted 'tinymce-templates' plugin.
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
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - count
	 *   - json
	 *   - yaml
	 * ---
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
	 *     # List active plugins on the site.
	 *     $ wp plugin list --status=active --format=json
	 *     [{"name":"dynamic-hostname","status":"active","update":"none","version":"0.4.2"},{"name":"tinymce-templates","status":"active","update":"none","version":"4.4.3"},{"name":"wp-multibyte-patch","status":"active","update":"none","version":"2.4"},{"name":"wp-total-hacks","status":"active","update":"none","version":"2.0.1"}]
	 *
	 *     # List plugins on each site in a network.
	 *     $ wp site list --field=url | xargs -I % wp plugin list --url=%
	 *     +---------+----------------+--------+---------+
	 *     | name    | status         | update | version |
	 *     +---------+----------------+--------+---------+
	 *     | akismet | active-network | none   | 3.1.11  |
	 *     | hello   | inactive       | none   | 1.6     |
	 *     +---------+----------------+--------+---------+
	 *     +---------+----------------+--------+---------+
	 *     | name    | status         | update | version |
	 *     +---------+----------------+--------+---------+
	 *     | akismet | active-network | none   | 3.1.11  |
	 *     | hello   | inactive       | none   | 1.6     |
	 *     +---------+----------------+--------+---------+
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
