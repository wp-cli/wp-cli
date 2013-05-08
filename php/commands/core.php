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
	 * @synopsis [--locale=<locale>] [--version=<version>] [--path=<path>] [--force]
	 */
	public function download( $args, $assoc_args ) {
		if ( !isset( $assoc_args['force'] ) && is_readable( ABSPATH . 'wp-load.php' ) )
			WP_CLI::error( 'WordPress files seem to already be present here.' );

		if ( !is_dir( ABSPATH ) ) {
			WP_CLI::line( sprintf( 'Creating directory %s', ABSPATH ) );
			WP_CLI::launch( sprintf( 'mkdir -p %s', escapeshellarg( ABSPATH ) ) );
		}

		if ( isset( $assoc_args['locale'] ) ) {
			exec( 'curl -s ' . escapeshellarg( 'https://api.wordpress.org/core/version-check/1.5/?locale=' . $assoc_args['locale'] ), $lines, $r );
			if ($r) exit($r);
			$download_url = str_replace( '.zip', '.tar.gz', $lines[2] );
			WP_CLI::line( sprintf( 'Downloading WordPress %s (%s)...', $lines[3], $lines[4] ) );
		} elseif ( isset( $assoc_args['version'] ) ) {
			$download_url = 'https://wordpress.org/wordpress-' . $assoc_args['version'] . '.tar.gz';
			WP_CLI::line( sprintf( 'Downloading WordPress %s (%s)...', $assoc_args['version'], 'en_US' ) );
		} else {
			$download_url = 'https://wordpress.org/latest.tar.gz';
			WP_CLI::line( sprintf( 'Downloading latest WordPress (%s)...', 'en_US' ) );
		}

		$silent = WP_CLI::get_config('quiet') ? '--silent ' : '';

		$cmd = "curl -f $silent %s | tar xz --strip-components=1 --directory=%s";
		WP_CLI::launch( Utils\esc_cmd( $cmd, $download_url, ABSPATH ) );

		WP_CLI::success( 'WordPress downloaded.' );
	}

	/**
	 * Set up a wp-config.php file.
	 *
	 * @synopsis --dbname=<name> --dbuser=<user> [--dbpass=<password>] [--dbhost=<host>] [--dbprefix=<prefix>]
	 */
	public function config( $args, $assoc_args ) {
		$_POST['dbname'] = $assoc_args['dbname'];
		$_POST['uname'] = $assoc_args['dbuser'];
		$_POST['pwd'] = isset( $assoc_args['dbpass'] ) ? $assoc_args['dbpass'] : '';
		$_POST['dbhost'] = isset( $assoc_args['dbhost'] ) ? $assoc_args['dbhost'] : 'localhost';
		$_POST['prefix'] = isset( $assoc_args['dbprefix'] ) ? $assoc_args['dbprefix'] : 'wp_';

		$_GET['step'] = 2;

		if ( WP_CLI::get_config('quiet') ) ob_start();
		require ABSPATH . '/wp-admin/setup-config.php';
		if ( WP_CLI::get_config('quiet') ) ob_end_clean();
	}

	/**
	 * Determine if the WordPress tables are installed.
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
	 * @synopsis --url=<url> --title=<site-title> [--admin_name=<username>] --admin_email=<email> --admin_password=<password>
	 */
	public function install( $args, $assoc_args ) {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		if ( is_blog_installed() ) {
			WP_CLI::error( 'WordPress is already installed.' );
		}

		extract( wp_parse_args( $assoc_args, array(
			'title' => '',
			'admin_name' => 'admin',
			'admin_email' => '',
			'admin_password' => ''
		) ), EXTR_SKIP );

		$public = true;

		$result = wp_install( $title, $admin_name, $admin_email, $public, '', $admin_password );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( 'Installation failed (' . WP_CLI::error_to_string($result) . ').' );
		} else {
			WP_CLI::success( 'WordPress installed successfully.' );
		}
	}

	/**
	 * Transform a single-site install into a multi-site install.
	 *
	 * @subcommand install-network
	 * @synopsis --title=<network-title> [--base=<url-path>]
	 */
	public function install_network( $args, $assoc_args ) {
		if ( is_multisite() )
			WP_CLI::error( 'This already is a multisite install.' );

		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		// need to register the multisite tables manually for some reason
		foreach ( $wpdb->tables( 'ms_global' ) as $table => $prefixed_table )
			$wpdb->$table = $prefixed_table;

		extract( wp_parse_args( $assoc_args, array(
			'base' => '/',
		) ) );

		$hostname = self::get_clean_basedomain();
		$subdomain_install = isset( $assoc_args['subdomains'] );

		install_network();

		$result = populate_network( 1, $hostname, get_option( 'admin_email' ), $assoc_args['title'], $base, $subdomain_install );

		if ( is_wp_error( $result ) ) {
			if ( $result->get_error_codes() === array( 'no_wildcard_dns' ) )
				WP_CLI::warning( __( 'Wildcard DNS may not be configured correctly.' ) );
			else
				WP_CLI::error( $result );
		}

		ob_start();
?>
define('MULTISITE', true);
define('SUBDOMAIN_INSTALL', <?php echo $subdomain_install ? 'true' : 'false'; ?>);
$base = '<?php echo $base; ?>';
define('DOMAIN_CURRENT_SITE', '<?php echo $hostname; ?>');
define('PATH_CURRENT_SITE', '<?php echo $base; ?>');
define('SITE_ID_CURRENT_SITE', 1);
define('BLOG_ID_CURRENT_SITE', 1);

<?php
		$ms_config = ob_get_clean();

		self::modify_wp_config( $ms_config );

		wp_mkdir_p( WP_CONTENT_DIR . '/blogs.dir' );

		WP_CLI::success( "Network installed. Don't forget to set up rewrite rules." );
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
	 * @synopsis [--extra]
	 */
	public function version( $args = array(), $assoc_args = array() ) {
		global $wp_version, $wp_db_version, $tinymce_version, $manifest_version;

		$color = '%G';
		$version_text = $wp_version;
		$version_types = array(
			'-RC' => array( 'release candidate', '%y' ),
			'-beta' => array( 'beta', '%B' ),
			'-' => array( 'in development', '%R' ),
		);

		foreach( $version_types as $needle => $type ) {
			if ( stristr( $wp_version, $needle ) ) {
				list( $version_text, $color ) = $type;
				$version_text = "$color$wp_version%n (stability: $version_text)";
				break;
			}
		}

		if ( isset( $assoc_args['extra'] ) ) {
			WP_CLI::line( "WordPress version:\t$version_text" );

			WP_CLI::line( "Database revision:\t$wp_db_version" );

			preg_match( '/(\d)(\d+)-/', $tinymce_version, $match );
			$human_readable_tiny_mce = $match? $match[1] . '.' . $match[2] : '';
			WP_CLI::line( "TinyMCE version:\t"  . ( $human_readable_tiny_mce? "$human_readable_tiny_mce ($tinymce_version)" : $tinymce_version ) );

			WP_CLI::line( "Manifest revision:\t$manifest_version" );
		} else {
			WP_CLI::line( $version_text );
		}
	}

	/**
	 * Update WordPress.
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
				WP_CLI::line( sprintf( 'Downloading WordPress %s (%s)...', $assoc_args['version'], 'en_US' ) );
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
	 * @synopsis [<path>] --dbname=<name> --dbuser=<user> [--dbpass=<password>]
	 */
	function init_tests( $args, $assoc_args ) {
		if ( isset( $args[0] ) )
			$tests_dir = trailingslashit( $args[0] );
		else
			$tests_dir = ABSPATH . 'unit-tests/';

		$assoc_args = wp_parse_args( $assoc_args, array(
			'dbpass' => '',
		) );

		// Download the test suite
		WP_CLI::launch( 'svn co https://unit-test.svn.wordpress.org/trunk/ ' . escapeshellarg( $tests_dir ) );

		// Create the database
		$query = sprintf( 'CREATE DATABASE IF NOT EXISTS `%s`', $assoc_args['dbname'] );

		Utils\run_mysql_query( $query, array(
			'host' => 'localhost',
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
		);

		$config_file = str_replace( array_keys( $replacements ), array_values( $replacements ), $config_file );

		$config_file_path = $tests_dir . 'wp-tests-config.php';

		file_put_contents( $config_file_path, $config_file );

		WP_CLI::success( "Created $config_file_path" );
	}
}

WP_CLI::add_command( 'core', 'Core_Command' );

