<?php

use \Composer\Semver\Comparator;
use \WP_CLI\Extractor;
use \WP_CLI\Utils;

/**
 * Download, install, update and manage a WordPress install.
 *
 * ## EXAMPLES
 *
 *     # Download WordPress core
 *     $ wp core download --locale=nl_NL
 *     Downloading WordPress 4.5.2 (nl_NL)...
 *     md5 hash verified: c5366d05b521831dd0b29dfc386e56a5
 *     Success: WordPress downloaded.
 *
 *     # Install WordPress
 *     $ wp core install --url=example.com --title=Example --admin_user=supervisor --admin_password=strongpassword --admin_email=info@example.com
 *     Success: WordPress installed successfully.
 *
 *     # Display the WordPress version
 *     $ wp core version
 *     4.5.2
 *
 * @package wp-cli
 */
class Core_Command extends WP_CLI_Command {

	/**
	 * Check for WordPress updates via Version Check API.
	 *
	 * Lists the most recent versions when there are updates available,
	 * or success message when up to date.
	 *
	 * ## OPTIONS
	 *
	 * [--minor]
	 * : Compare only the first two parts of the version number.
	 *
	 * [--major]
	 * : Compare only the first part of the version number.
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each update.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields. Defaults to version,update_type,package_url.
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
	 *     $ wp core check-update
	 *     +---------+-------------+-------------------------------------------------------------+
	 *     | version | update_type | package_url                                                 |
	 *     +---------+-------------+-------------------------------------------------------------+
	 *     | 4.5.2   | major       | https://downloads.wordpress.org/release/wordpress-4.5.2.zip |
	 *     +---------+-------------+-------------------------------------------------------------+
	 *
	 * @subcommand check-update
	 */
	function check_update( $_, $assoc_args ) {

		$updates = $this->get_updates( $assoc_args );
		if ( $updates ) {
			$updates = array_reverse( $updates );
			$formatter = new \WP_CLI\Formatter(
				$assoc_args,
				array( 'version', 'update_type', 'package_url' )
			);
			$formatter->display_items( $updates );
		} else if ( empty( $assoc_args['format'] ) || 'table' == $assoc_args['format'] ) {
			WP_CLI::success( "WordPress is at the latest version." );
		}
	}

	/**
	 * Download core WordPress files.
	 *
	 * Downloads and extracts WordPress core files to the specified path. Uses
	 * an archive file stored in cache if WordPress has been previously
	 * downloaded.
	 *
	 * ## OPTIONS
	 *
	 * [--path=<path>]
	 * : Specify the path in which to install WordPress.
	 *
	 * [--locale=<locale>]
	 * : Select which language you want to download.
	 *
	 * [--version=<version>]
	 * : Select which version you want to download. Accepts a version number, 'latest' or 'nightly'
	 *
	 * [--force]
	 * : Overwrites existing files, if present.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp core download --locale=nl_NL
	 *     Downloading WordPress 4.5.2 (nl_NL)...
	 *     md5 hash verified: c5366d05b521831dd0b29dfc386e56a5
	 *     Success: WordPress downloaded.
	 *
	 * @when before_wp_load
	 */
	public function download( $args, $assoc_args ) {

		$download_dir = ! empty( $assoc_args['path'] ) ? $assoc_args['path'] : ABSPATH;
		$wordpress_present = is_readable( $download_dir . 'wp-load.php' );

		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'force' ) && $wordpress_present )
			WP_CLI::error( 'WordPress files seem to already be present here.' );

		if ( ! is_dir( $download_dir ) ) {
			if ( ! is_writable( dirname( $download_dir ) ) ) {
				WP_CLI::error( sprintf( "Insufficient permission to create directory '%s'.", $download_dir ) );
			}

			WP_CLI::log( sprintf( "Creating directory '%s'.", $download_dir ) );
			$mkdir = \WP_CLI\Utils\is_windows() ? 'mkdir %s' : 'mkdir -p %s';
			WP_CLI::launch( Utils\esc_cmd( $mkdir, $download_dir ) );
		}

		if ( ! is_writable( $download_dir ) ) {
			WP_CLI::error( sprintf( "'%s' is not writable by current user.", $download_dir ) );
		}

		$locale = \WP_CLI\Utils\get_flag_value( $assoc_args, 'locale', 'en_US' );

		if ( isset( $assoc_args['version'] ) && 'latest' !== $assoc_args['version'] ) {
			$version = $assoc_args['version'];
			$version = ( in_array( strtolower( $version ), array( 'trunk', 'nightly' ) ) ? 'nightly' : $version );
			//nightly builds are only available in .zip format
			$ext     = ( 'nightly' === $version ? 'zip' : 'tar.gz' );
			$download_url = $this->get_download_url( $version, $locale, $ext );
		} else {
			$offer = $this->get_download_offer( $locale );
			if ( !$offer ) {
				WP_CLI::error( "The requested locale ($locale) was not found." );
			}
			$version = $offer['current'];
			$download_url = str_replace( '.zip', '.tar.gz', $offer['download'] );
		}

		if ( 'nightly' === $version && 'en_US' !== $locale ) {
			WP_CLI::error( 'Nightly builds are only available for the en_US locale.' );
		}

		$from_version = '';
		if ( file_exists( $download_dir . 'wp-includes/version.php' ) ) {
			global $wp_version;
			require_once( $download_dir . 'wp-includes/version.php' );
			$from_version = $wp_version;
		}

		WP_CLI::log( sprintf( 'Downloading WordPress %s (%s)...', $version, $locale ) );

		$path_parts = pathinfo( $download_url );
		$extension  = 'tar.gz';
		if ( 'zip' === $path_parts['extension'] ) {
			$extension  = 'zip';
			if ( ! class_exists( 'ZipArchive' ) ) {
				WP_CLI::error( 'Extracting a zip file requires ZipArchive.' );
			}
		}

		$cache = WP_CLI::get_cache();
		$cache_key = "core/wordpress-{$version}-{$locale}.{$extension}";
		$cache_file = $cache->has($cache_key);

		$bad_cache = false;
		if ( $cache_file ) {
			WP_CLI::log( "Using cached file '$cache_file'..." );
			try{
				Extractor::extract( $cache_file, $download_dir );
			} catch ( Exception $e ) {
				WP_CLI::warning( "Extraction failed, downloading a new copy..." );
				$bad_cache = true;
			}
		}

		if ( ! $cache_file || $bad_cache ) {
			// We need to use a temporary file because piping from cURL to tar is flaky
			// on MinGW (and probably in other environments too).
			$temp = \WP_CLI\Utils\get_temp_dir() . uniqid('wp_') . ".{$extension}";

			$headers = array('Accept' => 'application/json');
			$options = array(
				'timeout' => 600,  // 10 minutes ought to be enough for everybody
				'filename' => $temp
			);

			$response = Utils\http_request( 'GET', $download_url, null, $headers, $options );
			if ( 404 == $response->status_code ) {
				WP_CLI::error( "Release not found. Double-check locale or version." );
			} else if ( 20 != substr( $response->status_code, 0, 2 ) ) {
				WP_CLI::error( "Couldn't access download URL (HTTP code {$response->status_code})." );
			}

			if ( 'nightly' !== $version ) {
				$md5_response = Utils\http_request( 'GET', $download_url . '.md5' );
				if ( 20 != substr( $md5_response->status_code, 0, 2 ) ) {
					WP_CLI::error( "Couldn't access md5 hash for release (HTTP code {$response->status_code})." );
				}

				$md5_file = md5_file( $temp );

				if ( $md5_file === $md5_response->body ) {
					WP_CLI::log( 'md5 hash verified: ' . $md5_file );
				} else {
					WP_CLI::error( "md5 hash for download ({$md5_file}) is different than the release hash ({$md5_response->body})." );
				}
			} else {
				WP_CLI::warning( 'md5 hash checks are not available for nightly downloads.' );
			}

			try {
				Extractor::extract( $temp, $download_dir );
			} catch ( Exception $e ) {
				WP_CLI::error( "Couldn't extract WordPress archive. " . $e->getMessage() );
			}

			if ( 'nightly' !== $version ) {
				$cache->import( $cache_key, $temp );
			}
			unlink( $temp );
		}

		if ( $wordpress_present ) {
			$this->cleanup_extra_files( $from_version, $version, $locale );
		}

		WP_CLI::success( 'WordPress downloaded.' );
	}

	private static function _read( $url ) {
		$headers = array('Accept' => 'application/json');
		$response = Utils\http_request( 'GET', $url, null, $headers, array( 'timeout' => 30 ) );
		if ( 200 === $response->status_code ) {
			return $response->body;
		} else {
			WP_CLI::error( "Couldn't fetch response from {$url} (HTTP code {$response->status_code})." );
		}
	}

	private function get_download_offer( $locale ) {
		$out = unserialize( self::_read(
			'https://api.wordpress.org/core/version-check/1.6/?locale=' . $locale ) );

		$offer = $out['offers'][0];

		if ( $offer['locale'] != $locale ) {
			return false;
		}

		return $offer;
	}

	private static function get_initial_locale() {
		include ABSPATH . '/wp-includes/version.php';

		// @codingStandardsIgnoreStart
		if ( isset( $wp_local_package ) )
			return $wp_local_package;
		// @codingStandardsIgnoreEnd

		return '';
	}

	/**
	 * Generate a wp-config.php file.
	 *
	 * Creates a new wp-config.php with database constants, and verifies that
	 * the database constants are correct.
	 *
	 * ## OPTIONS
	 *
	 * --dbname=<dbname>
	 * : Set the database name.
	 *
	 * --dbuser=<dbuser>
	 * : Set the database user.
	 *
	 * [--dbpass=<dbpass>]
	 * : Set the database user password.
	 *
	 * [--dbhost=<dbhost>]
	 * : Set the database host.
	 * ---
	 * default: localhost
	 * ---
	 *
	 * [--dbprefix=<dbprefix>]
	 * : Set the database table prefix.
	 * ---
	 * default: wp_
	 * ---
	 *
	 * [--dbcharset=<dbcharset>]
	 * : Set the database charset.
	 * ---
	 * default: utf8
	 * ---
	 *
	 * [--dbcollate=<dbcollate>]
	 * : Set the database collation.
	 * ---
	 * default:
	 * ---
	 *
	 * [--locale=<locale>]
	 * : Set the WPLANG constant. Defaults to $wp_local_package variable.
	 *
	 * [--extra-php]
	 * : If set, the command copies additional PHP code into wp-config.php from STDIN.
	 *
	 * [--skip-salts]
	 * : If set, keys and salts won't be generated, but should instead be passed via `--extra-php`.
	 *
	 * [--skip-check]
	 * : If set, the database connection is not checked.
	 *
	 * [--force]
	 * : Overwrites existing files, if present.
	 *
	 * ## EXAMPLES
	 *
	 *     # Standard wp-config.php file
	 *     $ wp core config --dbname=testing --dbuser=wp --dbpass=securepswd --locale=ro_RO
	 *     Success: Generated 'wp-config.php' file.
	 *
	 *     # Enable WP_DEBUG and WP_DEBUG_LOG
	 *     $ wp core config --dbname=testing --dbuser=wp --dbpass=securepswd --extra-php <<PHP
	 *     $ define( 'WP_DEBUG', true );
	 *     $ define( 'WP_DEBUG_LOG', true );
	 *     $ PHP
	 *     Success: Generated 'wp-config.php' file.
	 *
	 *     # Avoid disclosing password to bash history by reading from password.txt
	 *     $ wp core config --dbname=testing --dbuser=wp --prompt=dbpass < password.txt
	 *     Success: Generated 'wp-config.php' file.
	 */
	public function config( $_, $assoc_args ) {
		global $wp_version;
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'force' ) && Utils\locate_wp_config() ) {
			WP_CLI::error( "The 'wp-config.php' file already exists." );
		}

		$versions_path = ABSPATH . 'wp-includes/version.php';
		include $versions_path;

		$defaults = array(
			'dbhost' => 'localhost',
			'dbpass' => '',
			'dbprefix' => 'wp_',
			'dbcharset' => 'utf8',
			'dbcollate' => '',
			'locale' => self::get_initial_locale()
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		if ( preg_match( '|[^a-z0-9_]|i', $assoc_args['dbprefix'] ) )
			WP_CLI::error( '--dbprefix can only contain numbers, letters, and underscores.' );

		// Check DB connection
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-check' ) ) {
			Utils\run_mysql_command( 'mysql --no-defaults', array(
				'execute' => ';',
				'host' => $assoc_args['dbhost'],
				'user' => $assoc_args['dbuser'],
				'pass' => $assoc_args['dbpass'],
			) );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'extra-php' ) === true ) {
			$assoc_args['extra-php'] = file_get_contents( 'php://stdin' );
		}

		// TODO: adapt more resilient code from wp-admin/setup-config.php
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-salts' ) ) {
			$assoc_args['keys-and-salts'] = self::_read(
				'https://api.wordpress.org/secret-key/1.1/salt/' );
		}

		if ( \WP_CLI\Utils\wp_version_compare( '4.0', '<' ) ) {
			$assoc_args['add-wplang'] = true;
		} else {
			$assoc_args['add-wplang'] = false;
		}

		$out = Utils\mustache_render( 'wp-config.mustache', $assoc_args );

		$bytes_written = file_put_contents( ABSPATH . 'wp-config.php', $out );
		if ( ! $bytes_written ) {
			WP_CLI::error( "Could not create new 'wp-config.php' file." );
		} else {
			WP_CLI::success( "Generated 'wp-config.php' file." );
		}
	}

	/**
	 * Check if WordPress is installed.
	 *
	 * Determines whether WordPress is installed by checking if the standard
	 * database tables are installed. Doesn't produce output; uses exit codes
	 * to communicate whether WordPress is installed.
	 *
	 * [--network]
	 * : Check if this is a multisite install.
	 *
	 * ## EXAMPLES
	 *
	 *     # Check whether WordPress is installed; exit status 0 if installed, otherwise 1
	 *     $ wp core is-installed
	 *     $ echo $?
	 *     1
	 *
	 *     # Bash script for checking whether WordPress is installed or not
	 *     if ! $(wp core is-installed); then
	 *         wp core install
	 *     fi
	 *
	 * @subcommand is-installed
	 */
	public function is_installed( $_, $assoc_args ) {

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'network' ) ) {
			if ( is_blog_installed() && is_multisite() ) {
				WP_CLI::halt( 0 );
			} else {
				WP_CLI::halt( 1 );
			}
		} else if ( is_blog_installed() ) {
			WP_CLI::halt( 0 );
		} else {
			WP_CLI::halt( 1 );
		}
	}

	/**
	 * Runs the standard WordPress installation process.
	 *
	 * Creates the WordPress tables in the database using the URL, title, and
	 * default admin user details provided. Performs the famous 5 minute install
	 * in seconds or less.
	 *
	 * Note: if you've installed WordPress in a subdirectory, then you'll need
	 * to `wp option update siteurl` after `wp core install`. For instance, if
	 * WordPress is installed in the `/wp` directory and your domain is wp.dev,
	 * then you'll need to run `wp option update siteurl http://wp.dev/wp` for
	 * your WordPress install to function properly.
	 *
	 * Note: When using custom user tables (e.g. `CUSTOM_USER_TABLE`), the admin
	 * email and password are ignored if the user_login already exists. If the
	 * user_login doesn't exist, a new user will be created.
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
	 * [--admin_password=<password>]
	 * : The password for the admin user. Defaults to randomly generated string.
	 *
	 * --admin_email=<email>
	 * : The email address for the admin user.
	 *
	 * [--skip-email]
	 * : Don't send an email notification to the new admin user.
	 *
	 * ## EXAMPLES
	 *
	 *     # Install WordPress in 5 seconds
	 *     $ wp core install --url=example.com --title=Example --admin_user=supervisor --admin_password=strongpassword --admin_email=info@example.com
	 *     Success: WordPress installed successfully.
	 *
	 *     # Install WordPress without disclosing admin_password to bash history
	 *     $ wp core install --url=example.com --title=Example --admin_user=supervisor --admin_email=info@example.com --prompt=admin_password < admin_password.txt
	 */
	public function install( $args, $assoc_args ) {
		if ( $this->_install( $assoc_args ) ) {
			WP_CLI::success( 'WordPress installed successfully.' );
		} else {
			WP_CLI::log( 'WordPress is already installed.' );
		}
	}

	/**
	 * Transform a single-site install into a WordPress multisite install.
	 *
	 * Creates the multisite database tables, and adds the multisite constants
	 * to wp-config.php.
	 *
	 * For those using WordPress with Apache, remember to update the `.htaccess`
	 * file with the appropriate multisite rewrite rules.
	 *
	 * ## OPTIONS
	 *
	 * [--title=<network-title>]
	 * : The title of the new network.
	 *
	 * [--base=<url-path>]
	 * : Base path after the domain name that each site url will start with.
	 * ---
	 * default: /
	 * ---
	 *
	 * [--subdomains]
	 * : If passed, the network will use subdomains, instead of subdirectories. Doesn't work with 'localhost'.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp core multisite-convert
	 *     Set up multisite database tables.
	 *     Added multisite constants to wp-config.php.
	 *     Success: Network installed. Don't forget to set up rewrite rules.
	 *
	 * @subcommand multisite-convert
	 * @alias install-network
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
	 * Install WordPress multisite from scratch.
	 *
	 * Creates the WordPress tables in the database using the URL, title, and
	 * default admin user details provided. Then, creates the multisite tables
	 * in the database and adds multisite constants to the wp-config.php.
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : The address of the new site.
	 *
	 * [--base=<url-path>]
	 * : Base path after the domain name that each site url in the network will start with.
	 * ---
	 * default: /
	 * ---
	 *
	 * [--subdomains]
	 * : If passed, the network will use subdomains, instead of subdirectories. Doesn't work with 'localhost'.
	 *
	 * --title=<site-title>
	 * : The title of the new site.
	 *
	 * --admin_user=<username>
	 * : The name of the admin user.
	 * ---
	 * default: admin
	 * ---
	 *
	 * [--admin_password=<password>]
	 * : The password for the admin user. Defaults to randomly generated string.
	 *
	 * --admin_email=<email>
	 * : The email address for the admin user.
	 *
	 * [--skip-email]
	 * : Don't send an email notification to the new admin user.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp core multisite-install --title="Welcome to the WordPress" \
	 *     > --admin_user="admin" --admin_password="password" \
	 *     > --admin_email="user@example.com"
	 *     Single site database tables already present.
	 *     Set up multisite database tables.
	 *     Added multisite constants to wp-config.php.
	 *     Success: Network installed. Don't forget to set up rewrite rules.
	 *
	 * @subcommand multisite-install
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

		if ( true === \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-email' )
			&& ! function_exists( 'wp_new_blog_notification' ) ) {
			function wp_new_blog_notification() {
				// Silence is golden
			}
		}

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		extract( wp_parse_args( $assoc_args, array(
			'title' => '',
			'admin_user' => '',
			'admin_email' => '',
			'admin_password' => ''
		) ), EXTR_SKIP );

		// Support prompting for the `--url=<url>`,
		// which is normally a runtime argument
		if ( isset( $assoc_args['url'] ) ) {
			WP_CLI::set_url( $assoc_args['url'] );
		}

		$public = true;

		// @codingStandardsIgnoreStart
		if ( !is_email( $admin_email ) ) {
			WP_CLI::error( "The '{$admin_email}' email address is invalid." );
		}

		$result = wp_install( $title, $admin_user, $admin_email, $public, '', $admin_password );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( 'Installation failed (' . WP_CLI::error_to_string($result) . ').' );
		}
		// @codingStandardsIgnoreEnd

		if ( ! empty( $GLOBALS['wpdb']->last_error ) ) {
			WP_CLI::error( 'Installation produced database errors, and may have partially or completely failed.' );
		}

		if ( empty( $admin_password ) ) {
			WP_CLI::log( "Admin password: {$result['password']}" );
		}

		// Confirm the uploads directory exists
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			WP_CLI::warning( $upload_dir['error'] );
		}

		return true;
	}

	private function _multisite_convert( $assoc_args ) {
		global $wpdb;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$domain = self::get_clean_basedomain();
		if ( 'localhost' === $domain && ! empty( $assoc_args['subdomains'] ) ) {
			WP_CLI::error( "Multisite with subdomains cannot be configured when domain is 'localhost'." );
		}

		// need to register the multisite tables manually for some reason
		foreach ( $wpdb->tables( 'ms_global' ) as $table => $prefixed_table )
			$wpdb->$table = $prefixed_table;

		install_network();

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

		// delete_site_option() cleans the alloptions cache to prevent dupe option
		delete_site_option( 'upload_space_check_disabled' );
		update_site_option( 'upload_space_check_disabled', 1 );

		if ( !is_multisite() ) {
			$subdomain_export = Utils\get_flag_value( $assoc_args, 'subdomains' ) ? 'true' : 'false';
			$ms_config = <<<EOT
define( 'WP_ALLOW_MULTISITE', true );
define( 'MULTISITE', true );
define( 'SUBDOMAIN_INSTALL', {$subdomain_export} );
\$base = '{$assoc_args['base']}';
define( 'DOMAIN_CURRENT_SITE', '{$domain}' );
define( 'PATH_CURRENT_SITE', '{$assoc_args['base']}' );
define( 'SITE_ID_CURRENT_SITE', 1 );
define( 'BLOG_ID_CURRENT_SITE', 1 );
EOT;

			$wp_config_path = Utils\locate_wp_config();
			if ( is_writable( $wp_config_path ) && self::modify_wp_config( $ms_config ) ) {
				WP_CLI::log( "Added multisite constants to 'wp-config.php'." );
			} else {
				WP_CLI::warning( "Multisite constants could not be written to 'wp-config.php'. You may need to add them manually:" . PHP_EOL . $ms_config );
			}
		}

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
		$config_contents = file_get_contents( $wp_config_path );
		if ( false === strpos( $config_contents, $token ) ) {
			return false;
		}

		list( $before, $after ) = explode( $token, $config_contents );

		$content = PHP_EOL . PHP_EOL . trim( $content ) . PHP_EOL . PHP_EOL;

		file_put_contents( $wp_config_path, $before . $content . $token . $after );
		return true;
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
	 * [--extra]
	 * : Show extended version information.
	 *
	 * ## EXAMPLES
	 *
	 *     # Display the WordPress version
	 *     $ wp core version
	 *     4.5.2
	 *
	 *     # Display WordPress version along with other information
	 *     $ wp core version --extra
	 *     WordPress version: 4.5.2
	 *     Database revision: 36686
	 *     TinyMCE version:   4.310 (4310-20160418)
	 *     Package language:  en_US
	 *
	 * @when before_wp_load
	 */
	public function version( $args = array(), $assoc_args = array() ) {
		$details = self::get_wp_details();

		// @codingStandardsIgnoreStart
		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'extra' ) ) {
			if ( preg_match( '/(\d)(\d+)-/', $details['tinymce_version'], $match ) ) {
				$human_readable_tiny_mce = $match[1] . '.' . $match[2];
			} else {
				$human_readable_tiny_mce = '';
			}

			echo \WP_CLI\Utils\mustache_render( 'versions.mustache', array(
				'wp-version'    => $details['wp_version'],
				'db-version'    => $details['wp_db_version'],
				'local-package' => ( empty( $details['wp_local_package'] ) ?
					'en_US'
					: $details['wp_local_package']
				),
				'mce-version'   => ( $human_readable_tiny_mce ?
					"$human_readable_tiny_mce ({$details['tinymce_version']})"
					: $details['tinymce_version']
				)
			) );
		} else {
			WP_CLI::line( $details['wp_version'] );
		}
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Verify WordPress files against WordPress.org's checksums.
	 *
	 * Downloads md5 checksums for the current version from WordPress.org, and
	 * compares those checksums against the currently installed files.
	 *
	 * For security, avoids loading WordPress when verifying checksums.
	 *
	 * ## OPTIONS
	 *
	 * [--version=<version>]
	 * : Verify checksums against a specific version of WordPress.
	 *
	 * [--locale=<locale>]
	 * : Verify checksums against a specific locale of WordPress.
	 *
	 * ## EXAMPLES
	 *
	 *     # Verify checksums
	 *     $ wp core verify-checksums
	 *     Success: WordPress install verifies against checksums.
	 *
	 *     # Verify checksums for given WordPress version
	 *     $ wp core verify-checksums --version=4.0
	 *     Success: WordPress install verifies against checksums.
	 *
	 *     # Verify checksums for given locale
	 *     $ wp core verify-checksums --locale=en_US
	 *     Success: WordPress install verifies against checksums.
	 *
	 *     # Verify checksums for given locale
	 *     $ wp core verify-checksums --locale=ja
	 *     Warning: File doesn't verify against checksum: wp-includes/version.php
	 *     Warning: File doesn't verify against checksum: readme.html
	 *     Warning: File doesn't verify against checksum: wp-config-sample.php
	 *     Error: WordPress install doesn't verify against checksums.
	 *
	 * @when before_wp_load
	 *
	 * @subcommand verify-checksums
	 */
	public function verify_checksums( $args, $assoc_args ) {
		global $wp_version, $wp_local_package;

		if ( ! empty( $assoc_args['version'] ) ) {
			$wp_version = $assoc_args['version'];
		}

		if ( ! empty( $assoc_args['locale'] ) ) {
			$wp_local_package = $assoc_args['locale'];
		}

		if ( empty( $wp_version ) ) {
			$details = self::get_wp_details();
			$wp_version = $details['wp_version'];

			if ( empty( $wp_local_package ) ) {
				$wp_local_package = $details['wp_local_package'];
			}
		}

		$checksums = self::get_core_checksums( $wp_version,
			! empty( $wp_local_package ) ? $wp_local_package : 'en_US' );

		if ( ! is_array( $checksums ) ) {
			WP_CLI::error( "Couldn't get checksums from WordPress.org." );
		}

		$has_errors = false;
		foreach ( $checksums as $file => $checksum ) {
			// Skip files which get updated
			if ( 'wp-content' == substr( $file, 0, 10 ) ) {
				continue;
			}

			if ( ! file_exists( ABSPATH . $file ) ) {
				WP_CLI::warning( "File doesn't exist: {$file}" );
				$has_errors = true;
				continue;
			}

			$md5_file = md5_file( ABSPATH . $file );
			if ( $md5_file !== $checksum ) {
				WP_CLI::warning( "File doesn't verify against checksum: {$file}" );
				$has_errors = true;
			}
		}

		$core_checksums_files = array_filter( array_keys( $checksums ), array( $this, 'only_core_files_filter' ) );
		$core_files           = $this->get_wp_core_files();
		$additional_files     = array_diff( $core_files, $core_checksums_files );

		if ( ! empty( $additional_files ) ) {
			foreach ( $additional_files as $additional_file ) {
				WP_CLI::warning( "File should not exist: {$additional_file}" );
			}
		}

		if ( ! $has_errors ) {
			WP_CLI::success( "WordPress install verifies against checksums." );
		} else {
			WP_CLI::error( "WordPress install doesn't verify against checksums." );
		}
	}

	private function get_wp_core_files() {
		$core_files = array();
		try {
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( ABSPATH, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach ( $files as $file_info ) {
				$pathname = substr( $file_info->getPathname(), strlen( ABSPATH ) );
				if ( $file_info->isFile() && ( 0 === strpos( $pathname, 'wp-admin/' ) || 0 === strpos( $pathname, 'wp-includes/' ) ) ) {
					$core_files[] = str_replace( ABSPATH, '', $file_info->getPathname() );
				}
			}
		} catch( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		return $core_files;
	}

	private function only_core_files_filter( $file ) {
		return ( 0 === strpos( $file, 'wp-admin/' ) || 0 === strpos( $file, 'wp-includes/' ) );
	}

	/**
	 * Get version information from `wp-includes/version.php`.
	 *
	 * @return array {
	 *     @type string $wp_version The WordPress version.
	 *     @type int $wp_db_version The WordPress DB revision.
	 *     @type string $tinymce_version The TinyMCE version.
	 *     @type string $wp_local_package The TinyMCE version.
	 * }
	 */
	private static function get_wp_details() {
		$versions_path = ABSPATH . 'wp-includes/version.php';

		if ( ! is_readable( $versions_path ) ) {
			WP_CLI::error(
				"This does not seem to be a WordPress install.\n" .
				"Pass --path=`path/to/wordpress` or run `wp core download`." );
		}

		$version_content = file_get_contents( $versions_path, null, null, 6, 2048 );

		$vars   = array( 'wp_version', 'wp_db_version', 'tinymce_version', 'wp_local_package' );
		$result = array();

		foreach ( $vars as $var_name ) {
			$result[ $var_name ] = self::find_var( $var_name, $version_content );
		}

		return $result;
	}

	/**
	 * Search for the value assigned to variable `$var_name` in PHP code `$code`.
	 *
	 * This is equivalent to matching the `\$VAR_NAME = ([^;]+)` regular expression and returning
	 * the first match either as a `string` or as an `integer` (depending if it's surrounded by
	 * quotes or not).
	 *
	 * @param string $var_name Variable name to search for.
	 * @param string $code PHP code to search in.
	 *
	 * @return int|string|null
	 */
	private static function find_var( $var_name, $code ) {
		$start = strpos( $code, '$' . $var_name . ' = ' );

		if ( ! $start ) {
			return null;
		}

		$start = $start + strlen( $var_name ) + 3;
		$end   = strpos( $code, ";", $start );

		$value = substr( $code, $start, $end - $start );

		if ( $value[0] = "'" ) {
			return trim( $value, "'" );
		} else {
			return intval( $value );
		}
	}

	/**
	 * Security copy of the core function with Requests - Gets the checksums for the given version of WordPress.
	 *
	 * @param string $version Version string to query.
	 * @param string $locale  Locale to query.
	 * @return bool|array False on failure. An array of checksums on success.
	 */
	private static function get_core_checksums( $version, $locale ) {
		$url = 'https://api.wordpress.org/core/checksums/1.0/?' . http_build_query( compact( 'version', 'locale' ), null, '&' );

		$options = array(
			'timeout' => 30
		);

		$headers = array(
			'Accept' => 'application/json'
		);
		$response = Utils\http_request( 'GET', $url, null, $headers, $options );

		if ( ! $response->success || 200 != $response->status_code )
			return false;

		$body = trim( $response->body );
		$body = json_decode( $body, true );

		if ( ! is_array( $body ) || ! isset( $body['checksums'] ) || ! is_array( $body['checksums'] ) )
			return false;

		return $body['checksums'];
	}

	/**
	 * Update WordPress to a newer version.
	 *
	 * Defaults to updating WordPress to the latest version.
	 *
	 * If you see "Error: Another update is currently in progress.", you may
	 * need to run `wp option delete core_updater.lock` after verifying another
	 * update isn't actually running.
	 *
	 * ## OPTIONS
	 *
	 * [<zip>]
	 * : Path to zip file to use, instead of downloading from wordpress.org.
	 *
	 * [--minor]
	 * : Only perform updates for minor releases (e.g. update from WP 4.3 to 4.3.3 instead of 4.4.2).
	 *
	 * [--version=<version>]
	 * : Update to a specific version, instead of to the latest version. Alternatively accepts 'nightly'.
	 *
	 * [--force]
	 * : Update even when installed WP version is greater than the requested version.
     *
     * [--locale=<locale>]
     * : Select which language you want to download.
	 *
	 * ## EXAMPLES
	 *
	 *     # Update WordPress
	 *     $ wp core update
	 *     Updating to version 4.5.2 (en_US)...
	 *     Downloading update from https://downloads.wordpress.org/release/wordpress-4.5.2-no-content.zip...
	 *     Unpacking the update...
	 *     Cleaning up files...
	 *     No files found that need cleaned up
	 *     Success: WordPress updated successfully.
	 *
	 *     # Update WordPress to latest version of 3.8 release
	 *     $ wp core update --version=3.8 ../latest.zip
	 *     Updating to version 3.8 ()...
	 *     Unpacking the update...
	 *     Cleaning up files...
	 *     File removed: wp-admin/js/tags-box.js
	 *     ...
	 *     File removed: wp-admin/js/updates.min.
	 *     377 files cleaned up
	 *     Success: WordPress updated successfully.
	 *
	 *     # Update WordPress to 3.1 forcefully
	 *     $ wp core update --version=3.1 --force
	 *     Updating to version 3.1 (en_US)...
	 *     Downloading update from https://wordpress.org/wordpress-3.1.zip...
	 *     Unpacking the update...
	 *     Warning: Failed to fetch checksums. Please cleanup files manually.
	 *     Success: WordPress updated successfully.
	 *
	 * @alias upgrade
	 */
	public function update( $args, $assoc_args ) {
		global $wp_version;

		$update = $from_api = null;
		$upgrader = 'WP_CLI\\CoreUpgrader';

		if ( 'trunk' === Utils\get_flag_value( $assoc_args, 'version' ) ) {
			$assoc_args['version'] = 'nightly';
		}

		if ( ! empty( $args[0] ) ) {

			// ZIP path or URL is given
			$upgrader = 'WP_CLI\\NonDestructiveCoreUpgrader';
			$version = \WP_CLI\Utils\get_flag_value( $assoc_args, 'version' );

			$update = (object) array(
				'response'      => 'upgrade',
				'current'       => $version,
				'download'      => $args[0],
				'packages'      => (object) array (
									'partial' => null,
									'new_bundled' => null,
									'no_content' => null,
									'full' => $args[0],
								),
				'version' => $version,
				'locale' => null
			);

		} else if ( empty( $assoc_args['version'] ) ) {

			// Update to next release
			wp_version_check();
			$from_api = get_site_transient( 'update_core' );

			if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'minor' ) ) {
				foreach( $from_api->updates as $offer ) {
					$sem_ver = Utils\get_named_sem_ver( $offer->version, $wp_version );
					if ( ! $sem_ver || 'patch' !== $sem_ver ) {
						continue;
					}
					$update = $offer;
					break;
				}
				if ( empty( $update ) ) {
					WP_CLI::success( 'WordPress is at the latest minor release.' );
					return;
				}
			} else {
				if ( ! empty( $from_api->updates ) ) {
					list( $update ) = $from_api->updates;
				}
			}

		} else if (	\WP_CLI\Utils\wp_version_compare( $assoc_args['version'], '<' )
			|| 'nightly' === $assoc_args['version']
			|| \WP_CLI\Utils\get_flag_value( $assoc_args, 'force' ) ) {

			// Specific version is given
			$version = $assoc_args['version'];
			$locale = \WP_CLI\Utils\get_flag_value( $assoc_args, 'locale', get_locale() );

			$new_package = $this->get_download_url($version, $locale);

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
				'version' => $version,
				'locale' => $locale
			);

		}

		if ( ! empty( $update )
			&& ( $update->version != $wp_version || \WP_CLI\Utils\get_flag_value( $assoc_args, 'force' ) ) ) {

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

			if ( $update->version ) {
				WP_CLI::log( "Updating to version {$update->version} ({$update->locale})..." );
			} else {
				WP_CLI::log( "Starting update..." );
			}

			$from_version = $wp_version;

			$GLOBALS['wp_cli_update_obj'] = $update;
			$result = Utils\get_upgrader( $upgrader )->upgrade( $update );
			unset( $GLOBALS['wp_cli_update_obj'] );

			if ( is_wp_error($result) ) {
				$msg = WP_CLI::error_to_string( $result );
				if ( 'up_to_date' != $result->get_error_code() ) {
					WP_CLI::error( $msg );
				} else {
					WP_CLI::success( $msg );
				}
			} else {

				if ( file_exists( ABSPATH . 'wp-includes/version.php' ) ) {
					include( ABSPATH . 'wp-includes/version.php' );
					$to_version = $wp_version;
				}

				$locale = \WP_CLI\Utils\get_flag_value( $assoc_args, 'locale', get_locale() );
				$this->cleanup_extra_files( $from_version, $to_version, $locale );

				WP_CLI::success( 'WordPress updated successfully.' );
			}

		} else {
			WP_CLI::success( 'WordPress is up to date.' );
		}
	}

	/**
	 * Run the WordPress database update procedure.
	 *
	 * [--network]
	 * : Update databases for all sites on a network
	 *
	 * [--dry-run]
	 * : Compare database versions without performing the update.
	 *
	 * ## EXAMPLES
	 *
	 *     # Update the WordPress database
	 *     $ wp core update-db
	 *     Success: WordPress database upgraded successfully from db version 36686 to 35700.
	 *
	 *     # Update databases for all sites on a network
	 *     $ wp core update-db --network
	 *     WordPress database upgraded successfully from db version 35700 to 29630 on example.com/
	 *     Success: WordPress database upgraded on 123/123 sites
	 *
	 * @subcommand update-db
	 */
	function update_db( $_, $assoc_args ) {
		global $wpdb, $wp_db_version, $wp_current_db_version;

		$network = Utils\get_flag_value( $assoc_args, 'network' );
		if ( $network && ! is_multisite() ) {
			WP_CLI::error( 'This is not a multisite install.' );
		}

		$dry_run = Utils\get_flag_value( $assoc_args, 'dry-run' );
		if ( $dry_run ) {
			WP_CLI::log( 'Performing a dry run, with no database modification.' );
		}

		if ( $network ) {
			$iterator_args = array(
				'table' => $wpdb->blogs,
				'where' => array( 'spam' => 0, 'deleted' => 0, 'archived' => 0 ),
			);
			$it = new \WP_CLI\Iterators\Table( $iterator_args );
			$success = $total = 0;
			$site_ids = array();
			foreach( $it as $blog ) {
				$total++;
				$site_ids[] = $blog->site_id;
				$url = $blog->domain . $blog->path;
				$cmd = "--url={$url} core update-db";
				if ( $dry_run ) {
					$cmd .= ' --dry-run';
				}
				$process = WP_CLI::runcommand( $cmd, array( 'return' => 'all' ) );
				if ( 0 == $process->return_code ) {
					// See if we can parse the stdout
					if ( preg_match( '#Success: (.+)#', $process->stdout, $matches ) ) {
						$message = rtrim( $matches[1], '.' );
						$message = "{$message} on {$url}";
					} else {
						$message = "Database upgraded successfully on {$url}";
					}
					WP_CLI::log( $message );
					$success++;
				} else {
					WP_CLI::warning( "Database failed to upgrade on {$url}" );
				}
			}
			if ( ! $dry_run && $total && $success == $total ) {
				foreach( array_unique( $site_ids ) as $site_id ) {
					update_metadata( 'site', $site_id, 'wpmu_upgrade_site', $wp_db_version );
				}
			}
			WP_CLI::success( sprintf( 'WordPress database upgraded on %d/%d sites.', $success, $total ) );
		} else {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			$wp_current_db_version = __get_option( 'db_version' );
			if ( $wp_db_version != $wp_current_db_version ) {
				if ( $dry_run ) {
					WP_CLI::success( "WordPress database will be upgraded from db version {$wp_current_db_version} to {$wp_db_version}." );
				} else {
					wp_upgrade();
					WP_CLI::success( "WordPress database upgraded successfully from db version {$wp_current_db_version} to {$wp_db_version}." );
				}
			} else {
				WP_CLI::success( "WordPress database already at latest db version {$wp_db_version}." );
			}
		}
	}

	/**
	 * Gets download url based on version, locale and desired file type.
	 *
	 * @param $version
	 * @param string $locale
	 * @param string $file_type
	 * @return string
	 */
	private function get_download_url( $version, $locale = 'en_US', $file_type = 'zip' ) {

		if ( 'nightly' === $version ) {
			if ( 'zip' === $file_type ) {
				return 'https://wordpress.org/nightly-builds/wordpress-latest.zip';
			} else {
				WP_CLI::error( 'Nightly builds are only available in .zip format.' );
			}
		}

		if ( 'en_US' === $locale ) {
			$url = 'https://wordpress.org/wordpress-' . $version . '.' . $file_type;

			return $url;
		} else {
			$url = sprintf(
				'https://%s.wordpress.org/wordpress-%s-%s.' . $file_type,
				substr($locale, 0, 2),
				$version,
				$locale
			);

			return $url;
		}
	}

	/**
	 * Returns update information
	 */
	private function get_updates( $assoc_args ) {
		wp_version_check();
		$from_api = get_site_transient( 'update_core' );
		if ( ! $from_api ) {
			return array();
		}

		$compare_version = str_replace( '-src', '', $GLOBALS['wp_version'] );

		$updates = array(
			'major'      => false,
			'minor'      => false,
			);
		foreach ( $from_api->updates as $offer ) {

			$update_type = Utils\get_named_sem_ver( $offer->version, $compare_version );
			if ( ! $update_type ) {
				continue;
			}

			// WordPress follow its own versioning which is roughly equivalent to semver
			if ( 'minor' === $update_type ) {
				$update_type = 'major';
			} else if ( 'patch' === $update_type ) {
				$update_type = 'minor';
			}

			if ( ! empty( $updates[ $update_type ] ) && ! Comparator::greaterThan( $offer->version, $updates[ $update_type ]['version'] ) ) {
				continue;
			}

			$updates[ $update_type ] = array(
				'version'     => $offer->version,
				'update_type' => $update_type,
				'package_url' => ! empty( $offer->packages->partial ) ? $offer->packages->partial : $offer->packages->full,
			);
		}

		foreach( $updates as $type => $value ) {
			if ( empty( $value ) ) {
				unset( $updates[ $type ] );
			}
		}

		foreach( array( 'major', 'minor' ) as $type ) {
			if ( true === \WP_CLI\Utils\get_flag_value( $assoc_args, $type ) ) {
				return ! empty( $updates[ $type ] ) ? array( $updates[ $type ] ) : false;
			}
		}
		return array_values( $updates );
	}

	private function cleanup_extra_files( $version_from, $version_to, $locale ) {
		if ( ! $version_from || ! $version_to ) {
			WP_CLI::warning( 'Failed to find WordPress version. Please cleanup files manually.' );
			return;
		}

		$old_checksums = self::get_core_checksums( $version_from, $locale ? $locale : 'en_US' );
		$new_checksums = self::get_core_checksums( $version_to, $locale ? $locale : 'en_US' );

		if ( empty( $old_checksums ) || empty( $new_checksums ) ) {
			WP_CLI::warning( 'Failed to fetch checksums. Please cleanup files manually.' );
			return;
		}

		$files_to_remove = array_diff( array_keys( $old_checksums ), array_keys( $new_checksums ) );

		if ( ! empty( $files_to_remove ) ) {
			WP_CLI::log( 'Cleaning up files...' );

			$count = 0;
			foreach ( $files_to_remove as $file ) {

				// wp-content should be considered user data
				if ( 0 === stripos( $file, 'wp-content' ) ) {
					continue;
				}

				if ( file_exists( ABSPATH . $file ) ) {
					unlink( ABSPATH . $file );
					WP_CLI::log( 'File removed: ' . $file );
					$count++;
				}
			}

			if ( $count ) {
				WP_CLI::log( number_format( $count ) . ' files cleaned up.' );
			} else {
				WP_CLI::log( 'No files found that need cleaned up.' );
			}
		}
	}

}

WP_CLI::add_command( 'core', 'Core_Command' );
