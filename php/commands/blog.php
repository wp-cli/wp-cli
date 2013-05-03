<?php

/**
 * Manage blog.
 *
 * @package wp-cli
 */
class Blog_Command extends WP_CLI_Command {

	/**
	 * Delete comments.
	 */
	private function _empty_comments() {
		global $wpdb;

		// Empty comments and comment cache
		$comment_ids = $wpdb->get_col( "SELECT comment_ID FROM $wpdb->comments" );
		foreach ( $comment_ids as $comment_id ) {
			wp_cache_delete( $comment_id, 'comment' );
			wp_cache_delete( $comment_id, 'comment_meta' );
		}
		$wpdb->query( "TRUNCATE $wpdb->comments" );
		$wpdb->query( "TRUNCATE $wpdb->commentmeta" );
	}

	/**
	 * Delete all posts.
	 */
	private function _empty_posts() {
		global $wpdb;

		// Empty posts and post cache
		$posts_query = "SELECT ID FROM $wpdb->posts";
		$posts = new WP_CLI\Iterators\Query( $posts_query, 10000 );

		$taxonomies = get_taxonomies();

		while ( $posts->valid() ) {
			$post_id = $posts->current()->ID;

			wp_cache_delete( $post_id, 'posts' );
			wp_cache_delete( $post_id, 'post_meta' );
			foreach ( $taxonomies as $taxonomy )
				wp_cache_delete( $post_id, "{$taxonomy}_relationships" );
			wp_cache_delete( $wpdb->blogid . '-' . $post_id, 'global-posts' );

			$posts->next();
		}
		$wpdb->query( "TRUNCATE $wpdb->posts" );
		$wpdb->query( "TRUNCATE $wpdb->postmeta" );
	}

	/**
	 * Delete terms, taxonomies, and tax relationships.
	 */
	private function _empty_taxonomies() {
		global $wpdb;

		// Empty taxonomies and terms
		$terms = $wpdb->get_results( "SELECT term_id, taxonomy FROM $wpdb->term_taxonomy" );
		$ids = array();
		foreach ( (array) $terms as $term ) {
			$taxonomies[] = $term->taxonomy;
			$ids[] = $term->term_id;
			wp_cache_delete( $term->term_id, $term->taxonomy );
		}

		$taxonomies = array_unique( $taxonomies );
		foreach ( $taxonomies as $taxonomy ) {
			if ( isset( $cleaned[$taxonomy] ) )
				continue;
			$cleaned[$taxonomy] = true;

			wp_cache_delete( 'all_ids', $taxonomy );
			wp_cache_delete( 'get', $taxonomy );
			delete_option( "{$taxonomy}_children" );
		}
		$wpdb->query( "TRUNCATE $wpdb->terms" );
		$wpdb->query( "TRUNCATE $wpdb->term_taxonomy" );
		$wpdb->query( "TRUNCATE $wpdb->term_relationships" );
	}

	/**
	 * Insert default terms.
	 */
	private function _insert_default_terms() {
		global $wpdb;

		// Default category
		$cat_name = __( 'Uncategorized' );

		/* translators: Default category slug */
		$cat_slug = sanitize_title( _x( 'Uncategorized', 'Default category slug' ) );

		if ( global_terms_enabled() ) {
			$cat_id = $wpdb->get_var( $wpdb->prepare( "SELECT cat_ID FROM {$wpdb->sitecategories} WHERE category_nicename = %s", $cat_slug ) );
			if ( $cat_id == null ) {
				$wpdb->insert( $wpdb->sitecategories, array('cat_ID' => 0, 'cat_name' => $cat_name, 'category_nicename' => $cat_slug, 'last_updated' => current_time('mysql', true)) );
				$cat_id = $wpdb->insert_id;
			}
			update_option('default_category', $cat_id);
		} else {
			$cat_id = 1;
		}

		$wpdb->insert( $wpdb->terms, array('term_id' => $cat_id, 'name' => $cat_name, 'slug' => $cat_slug, 'term_group' => 0) );
		$wpdb->insert( $wpdb->term_taxonomy, array('term_id' => $cat_id, 'taxonomy' => 'category', 'description' => '', 'parent' => 0, 'count' => 1));
	}

	/**
	 * Empty a blog of its content.
	 *
	 * @subcommand empty
	 * @synopsis [--yes]
	 */
	public function _empty( $args, $assoc_args ) {

		WP_CLI::confirm( 'Are you sure you want to empty the blog at ' . site_url() . '?', $assoc_args );

		$this->_empty_posts();
		$this->_empty_comments();
		$this->_empty_taxonomies();
		$this->_insert_default_terms();

		WP_CLI::success( 'The blog at ' . site_url() . ' was emptied.' );
	}
}

/**
 * Manage blogs in a multisite install.
 */
class MS_Blog_Command extends Blog_Command {

	/**
	 * Delete a blog in a multisite install.
	 *
	 * @synopsis [<blog-id>] [--slug=<slug>] [--yes] [--keep-tables]
	 */
	function delete( $args, $assoc_args ) {
		if ( isset( $assoc_args['slug'] ) ) {
			$blog = get_blog_details( trim( $assoc_args['slug'], '/' ) );
		} else {
			if ( empty( $args ) ) {
				WP_CLI::error( "Need to specify a blog id." );
			}

			$blog_id = $args[0];

			$blog = get_blog_details( $blog_id );
		}

		if ( !$blog ) {
			WP_CLI::error( "Blog not found." );
		}

		WP_CLI::confirm( "Are you sure you want to delete the $blog->siteurl blog?", $assoc_args );

		wpmu_delete_blog( $blog->blog_id, !isset( $assoc_args['keep-tables'] ) );

		WP_CLI::success( "The blog at $blog->siteurl was deleted." );
	}

	/**
	 * Get site (network) data for a given id.
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
	 * @synopsis --slug=<slug> [--title=<title>] [--email=<email>] [--site_id=<site-id>] [--private] [--porcelain]
	 */
	public function create( $_, $assoc_args ) {
		global $wpdb;

		$base = $assoc_args['slug'];
		$title = isset( $assoc_args['title'] ) ? $assoc_args['title'] : ucfirst( $base );

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

		$public = !isset( $assoc_args['private'] );

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

		if ( isset( $assoc_args['porcelain'] ) )
			WP_CLI::line( $id );
		else
			WP_CLI::success( "Blog $id created: $url" );
	}
}

$command_class = 'Blog_Command';
if ( function_exists( 'is_multisite' ) && is_multisite() )
	$command_class = 'MS_Blog_Command';

WP_CLI::add_command( 'blog', $command_class );

