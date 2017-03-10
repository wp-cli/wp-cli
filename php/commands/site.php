<?php

/**
 * Perform site-wide operations.
 *
 * ## EXAMPLES
 *
 *     # Create site
 *     $ wp site create --slug=example
 *     Success: Site 3 created: www.example.com/example/
 *
 *     # Output a simple list of site URLs
 *     $ wp site list --field=url
 *     http://www.example.com/
 *     http://www.example.com/subdir/
 *
 *     # Delete site
 *     $ wp site delete 123
 *     Are you sure you want to delete the 'http://www.example.com/example' site? [y/n] y
 *     Success: The site at 'http://www.example.com/example' was deleted.
 *
 * @package wp-cli
 */
class Site_Command extends \WP_CLI\CommandWithDBObject {

	protected $obj_type = 'site';
	protected $obj_id_key = 'blog_id';

	public function __construct() {
		$this->fetcher = new \WP_CLI\Fetchers\Site;
	}

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
		$taxonomies = array();
		foreach ( (array) $terms as $term ) {
			$taxonomies[] = $term->taxonomy;
			$ids[] = $term->term_id;
			wp_cache_delete( $term->term_id, $term->taxonomy );
		}

		$taxonomies = array_unique( $taxonomies );
		$cleaned = array();
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
		if ( ! empty( $wpdb->termmeta ) ) {
			$wpdb->query( "TRUNCATE $wpdb->termmeta" );
		}
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
	 * Empty a site of its content (posts, comments, terms, and meta).
	 *
	 * Truncates posts, comments, and terms tables to empty a site of its
	 * content. Doesn't affect site configuration (options) or users.
	 *
	 * If running a persistent object cache, make sure to flush the cache
	 * after emptying the site, as the cache values will be invalid otherwise.
	 *
	 * To also empty custom database tables, you'll need to hook into command
	 * execution:
	 *
	 * ```
	 * WP_CLI::add_hook( 'after_invoke:site empty', function(){
	 *     global $wpdb;
	 *     foreach( array( 'p2p', 'p2pmeta' ) as $table ) {
	 *         $table = $wpdb->$table;
	 *         $wpdb->query( "TRUNCATE $table" );
	 *     }
	 * });
	 * ```
	 *
	 * ## OPTIONS
	 *
	 * [--uploads]
	 * : Also delete *all* files in the site's in the uploads directory.
	 *
	 * [--yes]
	 * : Proceed to empty the site without a confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp site empty
	 *     Are you sure you want to empty the site at http://www.example.com of all posts, comments, and terms? [y/n] y
	 *     Success: The site at 'http://www.example.com' was emptied.
	 *
	 * @subcommand empty
	 */
	public function _empty( $args, $assoc_args ) {

		$upload_message = '';
		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'uploads' ) ) {
			$upload_message = ', and delete its uploads directory';
		}

		WP_CLI::confirm( "Are you sure you want to empty the site at '" . site_url() . "' of all posts, comments, and terms" . $upload_message . "?", $assoc_args );

		$this->_empty_posts();
		$this->_empty_comments();
		$this->_empty_taxonomies();
		$this->_insert_default_terms();

		if ( ! empty( $upload_message ) ) {
			$upload_dir = wp_upload_dir();
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $upload_dir['basedir'], RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			$files_to_unlink = $directories_to_delete = array();
			foreach ( $files as $fileinfo ) {
				$realpath = $fileinfo->getRealPath();
				// Don't clobber subsites when operating on the main site
				if ( is_main_site() && false !== stripos( $realpath, '/sites/' ) ) {
					continue;
				}
				if ( $fileinfo->isDir() ) {
					$directories_to_delete[] = $realpath;
				} else {
					$files_to_unlink[] = $realpath;
				}
			}
			foreach( $files_to_unlink as $file ) {
				unlink( $file );
			}
			foreach( $directories_to_delete as $directory ) {
				rmdir( $directory );
			}
			rmdir( $upload_dir['basedir'] );
		}

		WP_CLI::success( "The site at '" . site_url() . "' was emptied." );
	}

	/**
	 * Delete a site in a multisite install.
	 *
	 * ## OPTIONS
	 *
	 * [<site-id>]
	 * : The id of the site to delete. If not provided, you must set the --slug parameter.
	 *
	 * [--slug=<slug>]
	 * : Path of the blog to be deleted. Subdomain on subdomain installs, directory on subdirectory installs.
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message.
	 *
	 * [--keep-tables]
	 * : Delete the blog from the list, but don't drop it's tables.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp site delete 123
	 *     Are you sure you want to delete the http://www.example.com/example site? [y/n] y
	 *     Success: The site at 'http://www.example.com/example' was deleted.
	 */
	function delete( $args, $assoc_args ) {
		if ( !is_multisite() ) {
			WP_CLI::error( 'This is not a multisite install.' );
		}

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
			WP_CLI::error( "Site not found." );
		}

		$site_url = trailingslashit( $blog->siteurl );

		WP_CLI::confirm( "Are you sure you want to delete the '$site_url' site?", $assoc_args );

		wpmu_delete_blog( $blog->blog_id, ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'keep-tables' ) );

		WP_CLI::success( "The site at '$site_url' was deleted." );
	}

	/**
	 * Create a site in a multisite install.
	 *
	 * ## OPTIONS
	 *
	 * --slug=<slug>
	 * : Path for the new site. Subdomain on subdomain installs, directory on subdirectory installs.
	 *
	 * [--title=<title>]
	 * : Title of the new site. Default: prettified slug.
	 *
	 * [--email=<email>]
	 * : Email for Admin user. User will be created if none exists. Assignement to Super Admin if not included.
	 *
	 * [--network_id=<network-id>]
	 * : Network to associate new site with. Defaults to current network (typically 1).
	 *
	 * [--private]
	 * : If set, the new site will be non-public (not indexed)
	 *
	 * [--porcelain]
	 * : If set, only the site id will be output on success.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp site create --slug=example
	 *     Success: Site 3 created: http://www.example.com/example/
	 */
	public function create( $_, $assoc_args ) {
		if ( !is_multisite() ) {
			WP_CLI::error( 'This is not a multisite install.' );
		}

		global $wpdb, $current_site;

		$base = $assoc_args['slug'];
		$title = \WP_CLI\Utils\get_flag_value( $assoc_args, 'title', ucfirst( $base ) );

		$email = empty( $assoc_args['email'] ) ? '' : $assoc_args['email'];

		// Network
		if ( !empty( $assoc_args['network_id'] ) ) {
			$network = $this->_get_network( $assoc_args['network_id'] );
			if ( $network === false ) {
				WP_CLI::error( sprintf( 'Network with id %d does not exist.', $assoc_args['network_id'] ) );
			}
		}
		else {
			$network = $current_site;
		}

		$public = ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'private' );

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
			$newdomain = $base . '.' . preg_replace( '|^www\.|', '', $current_site->domain );
			$path      = $current_site->path;
			$url       = $newdomain;
		} else {
			$newdomain = $current_site->domain;
			$path      = $current_site->path . $base . '/';
			$url       = $newdomain . $path;
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
		$title = wp_slash( $title );
		$id = wpmu_create_blog( $newdomain, $path, $title, $user_id, array( 'public' => $public ), $network->id );
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

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain' ) ) {
			WP_CLI::line( $id );
		} else {
			$site_url = trailingslashit( get_site_url( $id ) );
			WP_CLI::success( "Site $id created: $site_url" );
		}
	}

	/**
	 * Get network data for a given id.
	 *
	 * @param int     $network_id
	 * @return bool|array False if no network found with given id, array otherwise
	 */
	private function _get_network( $network_id ) {
		global $wpdb;

		// Load network data
		$networks = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $wpdb->site WHERE id = %d", $network_id ) );

		if ( !empty( $networks ) ) {
			// Only care about domain and path which are set here
			return $networks[0];
		}

		return false;
	}

	/**
	 * List all sites in a multisite install.
	 *
	 * ## OPTIONS
	 *
	 * [--network=<id>]
	 * : The network to which the sites belong.
	 *
	 * [--<field>=<value>]
	 * : Filter by one or more fields (see "Available Fields" section). However,
	 * 'url' isn't an available filter, because it's created from domain + path.
	 *
	 * [--site__in=<value>]
	 * : Only list the sites with these blog_id values (comma-separated).
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each site.
	 *
	 * [--fields=<fields>]
	 * : Comma-separated list of fields to show.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - count
	 *   - ids
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each site:
	 *
	 * * blog_id
	 * * url
	 * * last_updated
	 * * registered
	 *
	 * These fields are optionally available:
	 *
	 * * site_id
	 * * domain
	 * * path
	 * * public
	 * * archived
	 * * mature
	 * * spam
	 * * deleted
	 * * lang_id
	 *
	 * ## EXAMPLES
	 *
	 *     # Output a simple list of site URLs
	 *     $ wp site list --field=url
	 *     http://www.example.com/
	 *     http://www.example.com/subdir/
	 *
	 * @subcommand list
	 */
	public function list_( $_, $assoc_args ) {
		if ( !is_multisite() ) {
			WP_CLI::error( 'This is not a multisite install.' );
		}

		global $wpdb;

		if ( isset( $assoc_args['fields'] ) ) {
			$assoc_args['fields'] = preg_split( '/,[ \t]*/', $assoc_args['fields'] );
		}

		$defaults = array(
			'format' => 'table',
			'fields' => array( 'blog_id', 'url', 'last_updated', 'registered' ),
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$where = array();
		$append = '';

		$site_cols = array( 'blog_id', 'last_updated', 'registered', 'site_id', 'domain', 'path', 'public', 'archived', 'mature', 'spam', 'deleted', 'lang_id' );
		foreach( $site_cols as $col ) {
			if ( isset( $assoc_args[ $col ] ) ) {
				$where[ $col ] = $assoc_args[ $col ];
			}
		}

		if ( isset( $assoc_args['site__in'] ) ) {
			$where['blog_id'] = explode( ',', $assoc_args['site__in'] );
			$append = "ORDER BY FIELD( blog_id, " . implode( ',', array_map( 'intval', $where['blog_id'] ) ) . " )";
		}

		if ( isset( $assoc_args['network'] ) ) {
			$where['site_id'] = $assoc_args['network'];
		}

		$iterator_args = array(
			'table' => $wpdb->blogs,
			'where' => $where,
			'append' => $append,
		);
		$it = new \WP_CLI\Iterators\Table( $iterator_args );

		$it = \WP_CLI\Utils\iterator_map( $it, function( $blog ) {
			$blog->url = trailingslashit( get_site_url( $blog->blog_id ) );
			return $blog;
		} );

		if ( ! empty( $assoc_args['format'] ) && 'ids' === $assoc_args['format'] ) {
			$sites = iterator_to_array( $it );
			$ids = wp_list_pluck( $sites, 'blog_id' );
			$formatter = new \WP_CLI\Formatter( $assoc_args, null, 'site' );
			$formatter->display_items( $ids );
		}
		else {
			$formatter = new \WP_CLI\Formatter( $assoc_args, null, 'site' );
			$formatter->display_items( $it );
		}
	}

	/**
	 * Archive one or more sites.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more IDs of sites to archive.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp site archive 123
	 *     Success: Site 123 archived.
	 */
	public function archive( $args ) {
		$this->update_site_status( $args, 'archived', 1 );
	}

	/**
	 * Unarchive one or more sites.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more IDs of sites to unarchive.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp site unarchive 123
	 *     Success: Site 123 unarchived.
	 */
	public function unarchive( $args ) {
		$this->update_site_status( $args, 'archived', 0 );
	}

	/**
	 * Activate one or more sites.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more IDs of sites to activate.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp site activate 123
	 *     Success: Site 123 activated.
	 */
	public function activate( $args ) {
		$this->update_site_status( $args, 'deleted', 0 );
	}

	/**
	 * Deactivate one or more sites.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more IDs of sites to deactivate.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp site deactivate 123
	 *     Success: Site 123 deactivated.
	 */
	public function deactivate( $args ) {
		$this->update_site_status( $args, 'deleted', 1 );
	}

	/**
	 * Mark one or more sites as spam.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more IDs of sites to be marked as spam.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp site spam 123
	 *     Success: Site 123 marked as spam.
	 */
	public function spam( $args ) {
		$this->update_site_status( $args, 'spam', 1 );
	}

	/**
	 * Remove one or more sites from spam.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more IDs of sites to remove from spam.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp site unspam 123
	 *     Success: Site 123 removed from spam.
	 *
	 * @subcommand unspam
	 */
	public function unspam( $args ) {
		$this->update_site_status( $args, 'spam', 0 );
	}

	private function update_site_status( $ids, $pref, $value ) {
		if ( $pref == 'archived' && $value == 1 ) {
			$action = 'archived';
		} else if ( $pref == 'archived' && $value == 0) {
			$action = 'unarchived';
		} else if ( $pref == 'deleted' && $value == 1 ) {
			$action = 'deactivated';
		} else if ( $pref == 'deleted' && $value == 0 ) {
			$action = 'activated';
		} else if ( $pref == 'spam' && $value == 1 ) {
			$action = 'marked as spam';
		} else if ( $pref == 'spam' && $value == 0 ) {
			$action = 'removed from spam';
		}

		foreach ( $ids as $site_id ) {
			$site = $this->fetcher->get_check( $site_id );

			if ( is_main_site( $site->blog_id ) ) {
				WP_CLI::warning( "You are not allowed to change the main site." );
				continue;
			}

			$old_value = get_blog_status( $site->blog_id, $pref );

			if ( $value == $old_value ) {
				WP_CLI::warning( "Site {$site->blog_id} already {$action}." );
				continue;
			}

			update_blog_status( $site->blog_id, $pref, $value );
			WP_CLI::success( "Site {$site->blog_id} {$action}." );
		}
	}
}

WP_CLI::add_command( 'site', 'Site_Command' );
