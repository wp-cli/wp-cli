<?php

use \WP_CLI\Utils;

/**
 * Download, install, update and otherwise manage WordPress proper.
 *
 * @package wp-cli
 */
class Core_Command extends WP_CLI_Command {

	/**
	 * Download core WordPress files.
	 *
	 * ## OPTIONS
	 *
	 * --locale=<locale>
	 * : Select which language you want to download.
	 *
	 * --version=<version>
	 * : Select which version you want to download.
	 *
	 * --force
	 * : Overwrites existing files, if present.
	 *
	 * ## EXAMPLES
	 *
	 *     wp core download --version=3.3
	 *
	 * @synopsis [--locale=<locale>] [--version=<version>] [--path=<path>] [--force]
	 *
	 * @when before_wp_load
	 */
	public function download( $args, $assoc_args ) {
		if ( !isset( $assoc_args['force'] ) && is_readable( ABSPATH . 'wp-load.php' ) )
			WP_CLI::error( 'WordPress files seem to already be present here.' );

		if ( !is_dir( ABSPATH ) ) {
			WP_CLI::log( sprintf( 'Creating directory %s', ABSPATH ) );
			WP_CLI::launch( sprintf( 'mkdir -p %s', escapeshellarg( ABSPATH ) ) );
		}

		if ( isset( $assoc_args['locale'] ) &&  isset( $assoc_args['version'] ) ) {
			$download_url = sprintf( 'https://%s.wordpress.org/wordpress-%s-%s.tar.gz',
				substr( $assoc_args['locale'], 0, 2 ), $assoc_args['version'], $assoc_args['locale'] );
			WP_CLI::log( sprintf( 'Downloading WordPress %s (%s)...', $assoc_args['version'], $assoc_args['locale'] ) );
		} else if ( isset( $assoc_args['locale'] ) ) {
			$offer = $this->get_download_offer( $assoc_args['locale'] );
			$download_url = str_replace( '.zip', '.tar.gz', $offer['download'] );
			WP_CLI::log( sprintf( 'Downloading WordPress %s (%s)...',
				$offer['current'], $offer['locale'] ) );
		} elseif ( isset( $assoc_args['version'] ) ) {
			$download_url = 'https://wordpress.org/wordpress-' . $assoc_args['version'] . '.tar.gz';
			WP_CLI::log( sprintf( 'Downloading WordPress %s (%s)...', $assoc_args['version'], 'en_US' ) );
		} else {
			$download_url = 'https://wordpress.org/latest.tar.gz';
			WP_CLI::log( sprintf( 'Downloading latest WordPress (%s)...', 'en_US' ) );
		}

		$silent = WP_CLI::get_config('quiet') || \cli\Shell::isPiped() ?
			'--silent ' : '';

		// We need to use a temporary file because piping from cURL to tar is flaky
		// on MinGW (and probably in other environments too).
		$temp = tempnam( sys_get_temp_dir(), "wp_" );

		$headers = array('Accept' => 'application/json');
		$options = array(
			'timeout' => 30,
			'filename' => $temp
		);

		try {
			$request = Requests::get( $download_url, $headers, $options );
		} catch( Requests_Exception $ex ) {
			// Handle SSL certificate issues gracefully
			$options['verify'] = false;
			try {
				$request = Requests::get( $download_url, $headers, $options );
			}
			catch( Requests_Exception $ex ) {
				WP_CLI::error( $ex->getMessage() );
			}
		}

		$cmd = "tar xz --strip-components=1 --directory=%s -f $temp && rm $temp";

		WP_CLI::launch( sprintf( $cmd, ABSPATH ) );

		WP_CLI::success( 'WordPress downloaded.' );
	}

	private static function _read( $url ) {
		$headers = array('Accept' => 'application/json');
		$options = array();

		$r = false;
		try {
			$request = Requests::get( $url, $headers, $options );
			$r = $request->body;
		} catch( Requests_Exception $ex ) {
			// Handle SSL certificate issues gracefully
			$options['verify'] = false;
			try {
				$request = Requests::get( $url, $headers, $options );
				$r = $request->body;
			} catch( Requests_Exception $ex ) {
				WP_CLI::error( $ex->getMessage() );
			}
		}

		return $r;
	}

	private function get_download_offer( $locale ) {
		$out = unserialize( self::_read(
			'https://api.wordpress.org/core/version-check/1.6/?locale=' . $locale ) );

		return $out['offers'][0];
	}

	private static function get_initial_locale() {
		include ABSPATH . '/wp-includes/version.php';

		if ( isset( $wp_local_package ) )
			return $wp_local_package;

		return '';
	}

	/**
	 * Set up a wp-config.php file.
	 *
	 * ## OPTIONS
	 *
	 * --dbname=<dbname>
	 * : Set the database name.
	 *
	 * --dbuser=<dbuser>
	 * : Set the database user.
	 *
	 * --dbpass=<dbpass>
	 * : Set the database user password.
	 *
	 * --dbhost=<dbhost>
	 * : Set the database host. Default: 'localhost'
	 *
	 * --dbprefix=<dbprefix>
	 * : Set the database table prefix. Default: 'wp_'
	 *
	 * --locale=<locale>
	 * : Set the WPLANG constant. Defaults to $wp_local_package variable.
	 *
	 * --extra-php
	 * : If set, the command reads additional PHP code from STDIN.
	 *
	 * ## EXAMPLES
	 *
	 *     # Standard wp-config.php file
	 *     wp core config --dbname=testing --dbuser=wp --dbpass=securepswd --locale=ro_RO
	 *
	 *     # Enable WP_DEBUG and WP_DEBUG_LOG
	 *     wp core config --dbname=testing --dbuser=wp --dbpass=securepswd --extra-php <<PHP
	 *     define( 'WP_DEBUG', true );
	 *     define( 'WP_DEBUG_LOG', true );
	 *     PHP
	 *
	 * @synopsis [--dbname=<name>] [--dbuser=<user>] [--dbpass=<password>] [--dbhost=<host>] [--dbprefix=<prefix>] [--locale=<locale>] [--extra-php] [--prompt]
	 */
	public function config( $_, $assoc_args ) {
		if ( Utils\locate_wp_config() ) {
			WP_CLI::error( "The 'wp-config.php' file already exists." );
		}
		
		if( ! isset( $assoc_args['prompt'] ) ) {
			if( ! isset( $assoc_args['dbname'] ) ) {
				WP_CLI::error( "missing --dbname parameter" );
			}
			if( ! isset( $assoc_args['dbuser'] ) ) {
				WP_CLI::error( "missing --dbuser parameter" );
			}
		}

		$defaults = array(
			'dbhost' => 'localhost',
			'dbpass' => '',
			'dbprefix' => 'wp_',
			'locale' => self::get_initial_locale()
		);
		
		if( isset( $assoc_args['prompt'] ) ) {
			WP_CLI::line( '(1/3) --dbname=' );
			$dbname = WP_CLI::ask();
			WP_CLI::line( '(2/3) --dbuser=' );
			$dbuser = WP_CLI::ask();
			WP_CLI::line( '(3/3) --dbpass=' );
			$dbpass = WP_CLI::ask();
			
			$assoc_args['dbname'] = $dbname;
			$assoc_args['dbuser'] = $dbuser;
			$assoc_args['dbpass'] = $dbpass;
		}
		
		$assoc_args = array_merge( $defaults, $assoc_args );
		
		if ( preg_match( '|[^a-z0-9_]|i', $assoc_args['dbprefix'] ) )
			WP_CLI::error( '--dbprefix can only contain numbers, letters, and underscores.' );

		// Check DB connection
		Utils\run_mysql_command( 'mysql --no-defaults', array(
			'execute' => ';',
			'host' => $assoc_args['dbhost'],
			'user' => $assoc_args['dbuser'],
			'pass' => $assoc_args['dbpass'],
		) );

		if ( isset( $assoc_args['extra-php'] ) ) {
			$assoc_args['extra-php'] = file_get_contents( 'php://stdin' );
		}

		// TODO: adapt more resilient code from wp-admin/setup-config.php

		$assoc_args['keys-and-salts'] = self::_read(
			'https://api.wordpress.org/secret-key/1.1/salt/' );

		$out = Utils\mustache_render( 'wp-config.mustache', $assoc_args );
		file_put_contents( ABSPATH . 'wp-config.php', $out );

		WP_CLI::success( 'Generated wp-config.php file.' );
	}

	/**
	 * Determine if the WordPress tables are installed.
	 *
	 * ## EXAMPLES
	 *
	 *     if ! $(wp core is-installed); then
	 *         wp core install
	 *     fi
	 *
	 * @subcommand is-installed
	 */
	public function is_installed() {
		if ( is_blog_installed() ) {
			exit( 0 );
		} else {
			exit( 1 );
		}
	}

	/**
	 * Create the WordPress tables in the database.
	 *
	 * ## OPTIONS
	 *
	 * --url=<url>
	 * : The address of the new site.
	 *
	 * --title=<site-title>
	 * : The title of the new site.
	 *
	 * --admin_user=<username>
	 * : The name of the admin user.
	 *
	 * --admin_password=<password>
	 * : The password for the admin user.
	 *
	 * --admin_email=<email>
	 * : The email address for the admin user.
	 *
	 * @synopsis --url=<url> --title=<site-title> --admin_user=<username> --admin_email=<email> --admin_password=<password>
	 */
	public function install( $args, $assoc_args ) {
		if ( $this->_install( $assoc_args ) ) {
			WP_CLI::success( 'WordPress installed successfully.' );
		} else {
			WP_CLI::log( 'WordPress is already installed.' );
		}
	}

	/**
	 * Transform a single-site install into a multi-site install.
	 *
	 * ## OPTIONS
	 *
	 * --title=<site-title>
	 * : The title of the new network.
	 *
	 * --base=<url-path>
	 * : Base path after the domain name that each site url will start with.
	 * Default: '/'
	 *
	 * --subdomains
	 * : If passed, the network will use subdomains, instead of subdirectories.
	 *
	 * @subcommand multisite-convert
	 * @alias install-network
	 * @synopsis [--title=<network-title>] [--base=<url-path>] [--subdomains]
	 */
	public function multisite_convert( $args, $assoc_args ) {
		if ( is_multisite() )
			WP_CLI::error( 'This already is a multisite install.' );

		$assoc_args = self::_set_multisite_defaults( $assoc_args );
		if ( !isset( $assoc_args['title'] ) ) {
			$assoc_args['title'] = sprintf( _x('%s Sites', 'Default network name' ), get_option( 'blogname' ) );
		}

		if ( $this->_multisite_convert( $assoc_args ) ) {
			WP_CLI::success( "Network installed. Don't forget to set up rewrite rules." );
		}
	}

	/**
	 * Install multisite from scratch.
	 *
	 * ## OPTIONS
	 *
	 * --url=<url>
	 * : The address of the new site.
	 *
	 * --base=<url-path>
	 * : Base path after the domain name that each site url in the network will start with.
	 * Default: '/'
	 *
	 * --subdomains
	 * : If passed, the network will use subdomains, instead of subdirectories.
	 *
	 * --title=<site-title>
	 * : The title of the new site.
	 *
	 * --admin_user=<username>
	 * : The name of the admin user. Default: 'admin'
	 *
	 * --admin_password=<password>
	 * : The password for the admin user.
	 *
	 * --admin_email=<email>
	 * : The email address for the admin user.
	 *
	 * @subcommand multisite-install
	 * @synopsis --url=<url> --title=<site-title> [--base=<url-path>] [--subdomains] --admin_user=<username> --admin_email=<email> --admin_password=<password>
	 */
	public function multisite_install( $args, $assoc_args ) {
		if ( $this->_install( $assoc_args ) ) {
			WP_CLI::log( 'Created single site database tables.' );
		} else {
			WP_CLI::log( 'Single site database tables already present.' );
		}

		$assoc_args = self::_set_multisite_defaults( $assoc_args );
		$assoc_args['title'] = sprintf( _x('%s Sites', 'Default network name' ), $assoc_args['title'] );

		// Overwrite runtime args, to avoid mismatches.
		$consts_to_args = array(
			'SUBDOMAIN_INSTALL' => 'subdomains',
			'PATH_CURRENT_SITE' => 'base',
			'SITE_ID_CURRENT_SITE' => 'site_id',
			'BLOG_ID_CURRENT_SITE' => 'blog_id',
		);

		foreach ( $consts_to_args as $const => $arg ) {
			if ( defined( $const ) ) {
				$assoc_args[ $arg ] = constant( $const );
			}
		}

		if ( !$this->_multisite_convert( $assoc_args ) ) {
			return;
		}

		// Do the steps that were skipped by populate_network(),
		// which checks is_multisite().
		if ( is_multisite() ) {
			$site_user = get_user_by( 'email', $assoc_args['admin_email'] );
			self::add_site_admins( $site_user );
			$domain = self::get_clean_basedomain();
			self::create_initial_blog(
				$assoc_args['site_id'],
				$assoc_args['blog_id'],
				$domain,
				$assoc_args['base'],
				$assoc_args['subdomains'],
				$site_user
			);
		}

		WP_CLI::success( "Network installed. Don't forget to set up rewrite rules." );
	}

	private static function _set_multisite_defaults( $assoc_args ) {
		$defaults = array(
			'subdomains' => false,
			'base' => '/',
			'site_id' => 1,
			'blog_id' => 1,
		);

		return array_merge( $defaults, $assoc_args );
	}

	private function _install( $assoc_args ) {
		if ( is_blog_installed() ) {
			return false;
		}

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		extract( wp_parse_args( $assoc_args, array(
			'title' => '',
			'admin_user' => '',
			'admin_email' => '',
			'admin_password' => ''
		) ), EXTR_SKIP );

		$public = true;

		$result = wp_install( $title, $admin_user, $admin_email, $public, '', $admin_password );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( 'Installation failed (' . WP_CLI::error_to_string($result) . ').' );
		}

		return true;
	}

	private function _multisite_convert( $assoc_args ) {
		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		// need to register the multisite tables manually for some reason
		foreach ( $wpdb->tables( 'ms_global' ) as $table => $prefixed_table )
			$wpdb->$table = $prefixed_table;

		install_network();

		$domain = self::get_clean_basedomain();
		$result = populate_network(
			$assoc_args['site_id'],
			$domain,
			get_option( 'admin_email' ),
			$assoc_args['title'],
			$assoc_args['base'],
			$assoc_args['subdomains']
		);

		if ( true === $result ) {
			WP_CLI::log( 'Set up multisite database tables.' );
		} else if ( is_wp_error( $result ) ) {
			switch ( $result->get_error_code() ) {

			case 'siteid_exists':
				WP_CLI::log( $result->get_error_message() );
				return false;

			case 'no_wildcard_dns':
				WP_CLI::warning( __( 'Wildcard DNS may not be configured correctly.' ) );
				break;

			default:
				WP_CLI::error( $result );
			}
		}

		if ( !is_multisite() ) {
			ob_start();
?>
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', <?php var_export( $assoc_args['subdomains'] ); ?>);
$base = '<?php echo $assoc_args['base']; ?>';
define('DOMAIN_CURRENT_SITE', '<?php echo $domain; ?>');
define('PATH_CURRENT_SITE', '<?php echo $assoc_args['base']; ?>');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);

<?php
			$ms_config = ob_get_clean();

			self::modify_wp_config( $ms_config );
			WP_CLI::log( 'Added multisite constants to wp-config.php.' );
		}

		wp_mkdir_p( WP_CONTENT_DIR . '/blogs.dir' );

		return true;
	}

	// copied from populate_network()
	private static function create_initial_blog( $network_id, $blog_id, $domain, $path,
		$subdomain_install, $site_user ) {
		global $wpdb, $current_site, $wp_rewrite;

		$current_site = new stdClass;
		$current_site->domain = $domain;
		$current_site->path = $path;
		$current_site->site_name = ucfirst( $domain );
		$wpdb->insert( $wpdb->blogs, array(
			'site_id' => $network_id,
			'domain' => $domain,
			'path' => $path,
			'registered' => current_time( 'mysql' )
		) );
		$current_site->blog_id = $blog_id = $wpdb->insert_id;
		update_user_meta( $site_user->ID, 'source_domain', $domain );
		update_user_meta( $site_user->ID, 'primary_blog', $blog_id );

		if ( $subdomain_install )
			$wp_rewrite->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		else
			$wp_rewrite->set_permalink_structure( '/blog/%year%/%monthnum%/%day%/%postname%/' );

		flush_rewrite_rules();
	}

	// copied from populate_network()
	private static function add_site_admins( $site_user ) {
		$site_admins = array( $site_user->user_login );
		$users = get_users( array( 'fields' => array( 'ID', 'user_login' ) ) );
		if ( $users ) {
			foreach ( $users as $user ) {
				if ( is_super_admin( $user->ID ) && !in_array( $user->user_login, $site_admins ) )
					$site_admins[] = $user->user_login;
			}
		}

		update_site_option( 'site_admins', $site_admins );
	}

	private static function modify_wp_config( $content ) {
		$wp_config_path = Utils\locate_wp_config();

		$token = "/* That's all, stop editing!";

		list( $before, $after ) = explode( $token, file_get_contents( $wp_config_path ) );

		file_put_contents( $wp_config_path, $before . $content . $token . $after );
	}

	private static function get_clean_basedomain() {
		$domain = preg_replace( '|https?://|', '', get_option( 'siteurl' ) );
		if ( $slash = strpos( $domain, '/' ) )
			$domain = substr( $domain, 0, $slash );
		return $domain;
	}

	/**
	 * Display the WordPress version.
	 *
	 * ## OPTIONS
	 *
	 * --extra
	 * : Show extended version information.
	 *
	 * @when before_wp_load
	 * @synopsis [--extra]
	 */
	public function version( $args = array(), $assoc_args = array() ) {
		$versions_path = ABSPATH . 'wp-includes/version.php';

		if ( !is_readable( $versions_path ) ) {
			WP_CLI::error(
				"This does not seem to be a WordPress install.\n" .
				"Pass --path=`path/to/wordpress` or run `wp core download`." );
		}

		include $versions_path;

		if ( isset( $assoc_args['extra'] ) ) {
			preg_match( '/(\d)(\d+)-/', $tinymce_version, $match );
			$human_readable_tiny_mce = $match ? $match[1] . '.' . $match[2] : '';

			echo \WP_CLI\Utils\mustache_render( 'versions.mustache', array(
				'wp-version' => $wp_version,
				'db-version' => $wp_db_version,
				'mce-version' => ( $human_readable_tiny_mce ?
					"$human_readable_tiny_mce ($tinymce_version)"
					: $tinymce_version
				)
			) );
		} else {
			WP_CLI::line( $wp_version );
		}
	}

	/**
	 * Update WordPress.
	 *
	 * ## OPTIONS
	 *
	 * --version=<new_version> [package/zip]
	 * : When passed, updates to new_version, optionally using package/zip as
	 * input.
	 *
	 * --force
	 * : Will update even when current WP version < passed version. Use with
	 * caution.
	 *
	 * ## EXAMPLES
	 *
	 *     wp core update
	 *
	 *     wp core update --version=3.4 ../latest.zip
	 *
	 *     wp core update --version=3.1 --force
	 *
	 * @alias upgrade
	 *
	 * @synopsis [<zip>] [--version=<version>] [--force]
	 */
	function update( $args, $assoc_args ) {
		global $wp_version;

		$update = $from_api = null;
		$upgrader = 'Core_Upgrader';

		if ( empty( $assoc_args['version'] ) ) {
			wp_version_check();
			$from_api = get_site_transient( 'update_core' );

			if ( empty( $from_api->updates ) )
				$update = false;
			else
				list( $update ) = $from_api->updates;

		} else if (	version_compare( $wp_version, $assoc_args['version'], '<' )
					|| isset( $assoc_args['force'] ) ) {

			$new_package = null;

			if ( empty( $args[0] ) ) {
				$new_package = 'https://wordpress.org/wordpress-' . $assoc_args['version'] . '.zip';
				WP_CLI::log( sprintf( 'Downloading WordPress %s (%s)...', $assoc_args['version'], 'en_US' ) );
			} else {
				$new_package = $args[0];
				$upgrader = 'WP_CLI\\NonDestructiveCoreUpgrader';
			}

			$update = (object) array(
				'response' => 'upgrade',
				'current' => $assoc_args['version'],
				'download' => $new_package,
				'packages' => (object) array (
					'partial' => null,
					'new_bundled' => null,
					'no_content' => null,
					'full' => $new_package,
				),
			);

		} else {
			WP_CLI::success( 'WordPress is up to date.' );
			return;
		}

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$result = Utils\get_upgrader( $upgrader )->upgrade( $update );

		if ( is_wp_error($result) ) {
			$msg = WP_CLI::error_to_string( $result );
			if ( 'up_to_date' != $result->get_error_code() ) {
				WP_CLI::error( $msg );
			} else {
				WP_CLI::success( $msg );
			}
		} else {
			WP_CLI::success( 'WordPress updated successfully.' );
		}
	}

	/**
	 * Update the WordPress database.
	 *
	 * @subcommand update-db
	 */
	function update_db() {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		wp_upgrade();
		WP_CLI::success( 'WordPress database upgraded successfully.' );
	}

	/**
	 * Set up the official test suite using the current WordPress instance.
	 *
	 * @subcommand init-tests
	 *
	 * ## OPTIONS
	 *
	 * <path>
	 * : The directory in which to download the testing suite files. (Optional)
	 *
	 * --dbname=<dbname>
	 * : Set the database name. **WARNING**: The database will be whipped every time
	 * you run the tests.
	 *
	 * --dbuser=<dbuser>
	 * : Set the database user.
	 *
	 * --dbpass=<dbpass>
	 * : Set the database user password.
	 *
	 * ## EXAMPLE
	 *
	 *     wp core init-tests ~/svn/wp-tests --dbname=wp_test --dbuser=wp_test
	 *
	 * @synopsis [<path>] --dbname=<name> --dbuser=<user> [--dbpass=<password>] [--dbhost=<host>]
	 */
	function init_tests( $args, $assoc_args ) {
		if ( isset( $args[0] ) )
			$tests_dir = trailingslashit( $args[0] );
		else
			$tests_dir = ABSPATH . 'unit-tests/';

		$assoc_args = wp_parse_args( $assoc_args, array(
			'dbpass' => '',
			'dbhost' => 'localhost'
		) );

		// Download the test suite
		WP_CLI::launch( 'svn co https://unit-test.svn.wordpress.org/trunk/ ' . escapeshellarg( $tests_dir ) );

		// Create the database
		$query = sprintf( 'CREATE DATABASE IF NOT EXISTS `%s`', $assoc_args['dbname'] );

		Utils\run_mysql_command( 'mysql --no-defaults', array(
			'execute' => $query,
			'host' => $assoc_args['dbhost'],
			'user' => $assoc_args['dbuser'],
			'pass' => $assoc_args['dbpass'],
		) );

		// Create the wp-tests-config.php file
		$config_file = file_get_contents( $tests_dir . 'wp-tests-config-sample.php' );

		$replacements = array(
			"dirname( __FILE__ ) . '/wordpress/'" => "'" . ABSPATH . "'",
			"yourdbnamehere"   => $assoc_args['dbname'],
			"yourusernamehere" => $assoc_args['dbuser'],
			"yourpasswordhere" => $assoc_args['dbpass'],
			"localhost" => $assoc_args['dbhost'],
		);

		$config_file = str_replace( array_keys( $replacements ), array_values( $replacements ), $config_file );

		$config_file_path = $tests_dir . 'wp-tests-config.php';

		file_put_contents( $config_file_path, $config_file );

		WP_CLI::success( "Created $config_file_path" );
	}
}

WP_CLI::add_command( 'core', 'Core_Command' );

