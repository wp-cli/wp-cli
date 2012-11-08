<?php

if ( is_multisite() ) {
	WP_CLI::add_command( 'blog', 'Blog_Command' );
}

/**
 * Implement core command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Blog_Command extends WP_CLI_Command {

	/**
	 * Get site (network) data for a given id
	 *
	 * @param int     $site_id
	 * @return bool|array False if no network found with given id, array otherwise
	 */
	private function _get_site( $site_id ) {
		global $wpdb;
		// Load site data
		$sites = $wpdb->get_results( "SELECT * FROM $wpdb->site WHERE `id` = ".$wpdb->escape( $site_id ) );
		if ( count( $sites ) > 0 ) {
			// Only care about domain and path which are set here
			return $sites[0];
		}

		return false;
	}

	/**
	 * Create a blog in a multisite install.
	 *
	 * @synopsis --slug=<slug> --title=<title> [--email=<email>] [--site_id=<site-id>] [--public]
	 */
	public function create( $args, $assoc_args ) {
		global $wpdb;

		$base = $assoc_args['slug'];
		$title = $assoc_args['title'];
		$email = empty( $assoc_args['email'] ) ? '' : $assoc_args['email'];
		// Site
		if ( !empty( $assoc_args['site_id'] ) ) {
			$site = $this->_get_site( $assoc_args['site_id'] );
			if ( $site === false ) {
				WP_CLI::error( 'Site with id '.$assoc_args['site_id'].' does not exist' );
			}
		}
		else {
			$site = wpmu_current_site();
		}

		$public = isset( $assoc_args['private'] ) ? 0 : 1;

		// Sanitize
		if ( preg_match( '|^([a-zA-Z0-9-])+$|', $base ) ) {
			$base = strtolower( $base );
		}

		// If not a subdomain install, make sure the domain isn't a reserved word
		if ( !is_subdomain_install() ) {
			$subdirectory_reserved_names = apply_filters( 'subdirectory_reserved_names', array( 'page', 'comments', 'blog', 'files', 'feed' ) );
			if ( in_array( $base, $subdirectory_reserved_names ) ) {
				WP_CLI::error( 'The following words are reserved and cannot be used as blog names: ' . implode( ', ', $subdirectory_reserved_names ) );
			}
		}

		// Check for valid email, if not, use the first Super Admin found
		// Probably a more efficient way to do this so we dont query for the
		// User twice if super admin
		$email = sanitize_email( $email );
		if ( empty( $email ) || !is_email( $email ) ) {
			$super_admins = get_super_admins();
			$email = '';
			if ( !empty( $super_admins ) && is_array( $super_admins ) ) {
				// Just get the first one
				$super_login = $super_admins[0];
				$super_user = get_user_by( 'login', $super_login );
				if ( $super_user ) {
					$email = $super_user->user_email;
				}
			}
		}

		if ( is_subdomain_install() ) {
			$path = '/';
			$url = $newdomain = $base.'.'.preg_replace( '|^www\.|', '', $site->domain );
		}
		else {
			$newdomain = $site->domain;
			$path = '/' . trim( $base, '/' ) . '/';
			$url = $site->domain . $path;
		}

		$user_id = email_exists( $email );
		if ( !$user_id ) { // Create a new user with a random password
			$password = wp_generate_password( 12, false );
			$user_id = wpmu_create_user( $base, $password, $email );
			if ( false == $user_id ) {
				WP_CLI::error( "Can't create user." );
			}
			else {
				wp_new_user_notification( $user_id, $password );
			}
		}

		$wpdb->hide_errors();
		$id = wpmu_create_blog( $newdomain, $path, $title, $user_id, array( 'public' => $public ), $site->id );
		$wpdb->show_errors();
		if ( !is_wp_error( $id ) ) {
			if ( !is_super_admin( $user_id ) && !get_user_option( 'primary_blog', $user_id ) ) {
				update_user_option( $user_id, 'primary_blog', $id, true );
			}
			// Prevent mailing admins of new sites
			// @TODO argument to pass in?
			// $content_mail = sprintf(__( "New site created by WP Command Line Interface\n\nAddress: %2s\nName: %3s"), get_site_url($id), stripslashes($title));
			// wp_mail(get_site_option('admin_email'), sprintf(__('[%s] New Site Created'), $current_site->site_name), $content_mail, 'From: "Site Admin" <'.get_site_option( 'admin_email').'>');
		}
		else {
			WP_CLI::error( $id->get_error_message() );
		}
		WP_CLI::success( "Blog $id created: $url" );
	}

	/**
	 * Delete a blog in a multisite install.
	 *
	 * @synopsis --slug=<slug> [--yes] [--keep-tables]
	 */
	function delete( $_, $assoc_args ) {
		$slug = '/' . trim( $assoc_args['slug'], '/' ) . '/';

		$blog_id = self::get_blog_id_by_slug( $slug );

		if ( !$blog_id )
			WP_CLI::error( sprintf( "'%s' blog not found.", $slug ) );

		WP_CLI::confirm( "Are you sure you want to delete the '$slug' blog?", $assoc_args );

		wpmu_delete_blog( $blog_id, !isset( $assoc_args['keep-tables'] ) );

		WP_CLI::success( "Blog '$slug' deleted." );
	}

	protected static function get_blog_id_by_slug( $slug ) {
		global $wpdb, $current_site;

		return $wpdb->get_var( $wpdb->prepare( "
			SELECT blog_id FROM $wpdb->blogs WHERE domain = %s AND path = %s"
		, $current_site->domain, $slug ) );
	}
}

