<?php

WP_CLI::add_command('core', 'Core_Command');

/**
 * Implement core command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Core_Command extends WP_CLI_Command {

	/**
	 * Download the core files from wordpress.org
	 */
	public function download( $args, $assoc_args ) {
		if ( is_readable( WP_ROOT . 'wp-load.php' ) )
			WP_CLI::error( 'WordPress files seem to already be present here.' );

		if ( isset( $assoc_args['path'] ) )
			$docroot = $assoc_args['path'];
		else
			$docroot = './';

		if ( isset( $assoc_args['locale'] ) ) {
			exec( 'curl -s ' . escapeshellarg( 'http://api.wordpress.org/core/version-check/1.5/?locale=' . $assoc_args['locale'] ), $lines, $r );
			if ($r) exit($r);
			$download_url = $lines[2];
			WP_CLI::line( sprintf( 'Downloading WordPress %s (%s)...', $lines[3], $lines[4] ) );
		} elseif ( isset( $assoc_args['version'] ) ) {
			$download_url = 'http://wordpress.org/wordpress-' . $assoc_args['version'] . '.zip';
			WP_CLI::line( sprintf( 'Downloading WordPress %s (%s)...', $assoc_args['version'], 'en_US' ) );
		} else {
			$download_url = 'http://wordpress.org/latest.zip';
			WP_CLI::line( sprintf( 'Downloading latest WordPress (%s)...', 'en_US' ) );
		}

		WP_CLI::launch( 'curl -f' . (WP_CLI_SILENT ? ' --silent ' : ' ') . escapeshellarg( $download_url ) . ' > /tmp/wordpress.zip' );
		WP_CLI::launch( 'unzip ' . (WP_CLI_SILENT ? '-qq ' : '-q ') . '/tmp/wordpress.zip' );
		WP_CLI::launch( 'mv wordpress/* ' . escapeshellarg( $docroot ) );
		WP_CLI::launch( 'rm -r wordpress' );

		WP_CLI::success( 'WordPress downloaded.' );
	}

	/**
	 * Set up a wp-config.php file.
	 */
	public function config( $args, $assoc_args ) {
		WP_CLI::check_required_args( array( 'dbname', 'dbuser', 'dbpass' ), $assoc_args );

		$_POST['dbname'] = $assoc_args['dbname'];
		$_POST['uname'] = $assoc_args['dbuser'];
		$_POST['pwd'] = $assoc_args['dbpass'];
		$_POST['dbhost'] = isset( $assoc_args['dbhost'] ) ? $assoc_args['dbhost'] : 'localhost';
		$_POST['prefix'] = isset( $assoc_args['dbprefix'] ) ? $assoc_args['dbprefix'] : 'wp_';

		$_GET['step'] = 2;

		if ( WP_CLI_SILENT ) ob_start();
		require WP_ROOT . '/wp-admin/setup-config.php';
		if ( WP_CLI_SILENT ) ob_end_clean();
	}

	/**
	 * Run wp_install. Assumes that wp-config.php is already in place.
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
			'admin_password' => wp_generate_password( 12, false )
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
	 * Display the WordPress version.
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
	 * Update the WordPress core
	 *
	 * @param array $args
	 */
	function update( $args, $assoc_args ) {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		if ( isset( $assoc_args['db'] ) ) {
			wp_upgrade();
			WP_CLI::success( 'WordPress database upgraded successfully.' );
			return;
		}

		wp_version_check();

		$from_api = get_site_transient( 'update_core' );

		if ( empty( $from_api->updates ) )
			$update = false;
		else
			list( $update ) = $from_api->updates;

		$result = WP_CLI::get_upgrader( 'Core_Upgrader' )->upgrade( $update );

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
}

