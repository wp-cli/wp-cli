<?php

class Export_Command extends WP_CLI_Command {

	/**
	* Initialize the array of arguments that will be eventually be passed to export_wp
	* @var array
	*/
	public $export_args = array();

	/**
	 * Export content to a WXR file.
	 *
	 * @synopsis [--dir=<dir>] [--start_date=<date>] [--end_date=<date>] [--post_type=<ptype>] [--post_status=<status>] [--post__in=<pids>] [--author=<login>] [--category=<cat>] [--skip_comments] [--file_item_count=<count>] [--verbose]
	 */
	public function __invoke( $_, $assoc_args ) {
		$defaults = array(
			'dir'             => NULL,
			'start_date'      => NULL,
			'end_date'        => NULL,
			'post_type'       => NULL,
			'author'          => NULL,
			'category'        => NULL,
			'post_status'     => NULL,
			'post__in'        => NULL,
			'skip_comments'   => NULL,
			'file_item_count' => 1000,
			'verbose'         => false,
		);

		$args = wp_parse_args( $assoc_args, $defaults );

		$has_errors = false;

		foreach ( $args as $key => $value ) {
			if ( is_callable( array( $this, 'check_' . $key ) ) ) {
				$result = call_user_func( array( $this, 'check_' . $key ), $value );
				if ( false === $result )
					$has_errors = true;
			}
		}

		if ( $has_errors ) {
			exit(1);
		}

		$this->wxr_path = trailingslashit( $this->export_args['dir'] );
		unset( $this->export_args['dir'] );

		WP_CLI::line( 'Starting export process...' );
		WP_CLI::line();
		$this->export_wp( $this->export_args );
	}

	private function check_verbose( $verbose ) {
		$this->verbose = $verbose;

		return true;
	}

	private function check_dir( $path ) {
		if ( empty( $path ) ) {
			$this->export_args['dir'] = getcwd();
			return true;
		}

		if ( !is_dir( $path ) ) {
			WP_CLI::error( sprintf( "The directory %s does not exist", $path ) );
		}

		$this->export_args['dir'] = $path;
		return true;
	}

	private function check_start_date( $date ) {
		if ( is_null( $date ) )
			return true;

		$time = strtotime( $date );
		if ( !empty( $date ) && !$time ) {
			WP_CLI::warning( sprintf( "The start_date %s is invalid", $date ) );
			return false;
		}
		$this->export_args['start_date'] = date( 'Y-m-d', $time );
		return true;
	}

	private function check_end_date( $date ) {
		if ( is_null( $date ) )
			return true;

		$time = strtotime( $date );
		if ( !empty( $date ) && !$time ) {
			WP_CLI::warning( sprintf( "The end_date %s is invalid", $date ) );
			return false;
		}
		$this->export_args['end_date'] = date( 'Y-m-d', $time );
		return true;
	}

	private function check_post_type( $post_type ) {
		if ( is_null( $post_type ) )
			return true;

		$post_types = get_post_types();
		if ( !in_array( $post_type, $post_types ) ) {
			WP_CLI::warning( sprintf( 'The post type %s does not exists. Choose "all" or any of these existing post types instead: %s', $post_type, implode( ", ", $post_types ) ) );
			return false;
		}
		$this->export_args['post_type'] = $post_type;
		return true;
	}

	private function check_post__in( $post__in ) {
		if ( is_null( $post__in ) )
			return true;

		$post__in = array_unique( array_map( 'intval', explode( ',', $post__in ) ) );
		if ( empty( $post__in ) ) {
			WP_CLI::warning( "post__in should be comma-separated post IDs" );
			return false;
		}
		$this->export_args['post__in'] = implode( ',', $post__in );
		return true;
	}

	private function check_author( $author ) {
		if ( is_null( $author ) )
			return true;

		$authors = get_users_of_blog();
		if ( empty( $authors ) || is_wp_error( $authors ) ) {
			WP_CLI::warning( sprintf( "Could not find any authors in this blog" ) );
			return false;
		}
		$hit = false;
		foreach( $authors as $user ) {
			if ( $hit )
				break;
			if ( (int) $author == $user->ID || $author == $user->user_login )
				$hit = $user->ID;
		}
		if ( false === $hit ) {
			$authors_nice = array();
			foreach( $authors as $_author )
				$authors_nice[] = sprintf( '%s (%s)', $_author->user_login, $_author->display_name );
			WP_CLI::warning( sprintf( 'Could not find a matching author for %s. The following authors exist: %s', $author, implode( ", ", $authors_nice ) ) );
			return false;
		}

		$this->export_args['author'] = $hit;
		return true;
	}

	private function check_category( $category ) {
		if ( is_null( $category ) )
			return true;

		$term = category_exists( $category );
		if ( empty( $term ) || is_wp_error( $term ) ) {
			WP_CLI::warning( sprintf( 'Could not find a category matching %s', $category ) );
			return false;
		}
		$this->export_args['category'] = $category;
		return true;
	}

	private function check_post_status( $status ) {
		if ( is_null( $status ) )
			return true;

		$stati = get_post_statuses();
		if ( empty( $stati ) || is_wp_error( $stati ) ) {
			WP_CLI::warning( 'Could not find any post stati' );
			return false;
		}

		if ( !isset( $stati[$status] ) ) {
			WP_CLI::warning( sprintf( 'Could not find a post_status matching %s. Here is a list of available stati: %s', $status, implode( ", ", array_keys( $stati ) ) ) );
			return false;
		}
		$this->export_args['status'] = $status;
		return true;
	}

	private function check_skip_comments( $skip ) {
		if ( is_null( $skip ) )
			return true;

		if ( (int) $skip <> 0 && (int) $skip <> 1 ) {
			WP_CLI::warning( 'skip_comments needs to be 0 (no) or 1 (yes)' );
			return false;
		}
		$this->export_args['skip_comments'] = $skip;
		return true;
	}

	private function check_file_item_count( $file_item_count ) {

		if ( ! is_numeric( $file_item_count ) ) {
			WP_CLI::warning( 'File item count needs to be numeric' );
			return false;
		}
		$this->export_args['file_item_count'] = $file_item_count;
		return true;
	}


	/**
	 * Workaround to prevent memory leaks from growing variables
	 */

	private function stop_the_insanity() {
		global $wpdb, $wp_object_cache;
		$wpdb->queries = array(); // or define( 'WP_IMPORTING', true );
		if ( !is_object( $wp_object_cache ) )
			return;
		$wp_object_cache->group_ops = array();
		$wp_object_cache->stats = array();
		$wp_object_cache->memcache_debug = array();
		$wp_object_cache->cache = array();
		if ( method_exists( $wp_object_cache, '__remoteset' ) )
			$wp_object_cache->__remoteset();
	}

	private function start_export() {
		ob_start();
	}

	private function end_export() {
		ob_end_clean();
	}

	private function flush_export( $file_path, $append = true ) {
		$result = ob_get_clean();
		if ( $append )
			$append = FILE_APPEND;
		file_put_contents( $file_path, $result, $append );
		$this->start_export();
	}

	/**
	 * Export function as it is defined in the original code of export_wp defined in wp-admin/includes/export.php
	 */

	private function export_wp( $args = array() ) {
		require_once ABSPATH . 'wp-admin/includes/export.php';

		global $wpdb;

		/**
		 * This is mostly the original code of export_wp defined in wp-admin/includes/export.php
		 */
		$defaults = array( 'post_type' => 'all', 'post__in' => false, 'author' => false, 'category' => false,
			'start_date' => false, 'end_date' => false, 'status' => false, 'skip_comments' => false, 'file_item_count' => 1000,
		);
		$args = wp_parse_args( $args, $defaults );

		if ( $this->verbose ) {
			WP_CLI::line( "Exporting with export_wp with arguments: " . var_export( $args, true ) );
		}

		do_action( 'export_wp' );

		$sitename = sanitize_key( get_bloginfo( 'name' ) );
		if ( ! empty( $sitename ) )
			$sitename .= '.';

		$append = array( date( 'Y-m-d' ) );
		foreach( array_keys( $args ) as $arg_key ) {
			if ( $defaults[$arg_key] <> $args[$arg_key] && 'post__in' != $arg_key )
				$append[]= "$arg_key-" . (string) $args[$arg_key];
		}
		$file_name_base = sanitize_file_name( $sitename . 'wordpress.' . implode( ".", $append ) );

		if ( 'all' != $args['post_type'] && post_type_exists( $args['post_type'] ) ) {
			$ptype = get_post_type_object( $args['post_type'] );
			if ( ! $ptype->can_export )
				$args['post_type'] = 'post';

			$where = $wpdb->prepare( "{$wpdb->posts}.post_type = %s", $args['post_type'] );
		} else {
			$post_types = get_post_types( array( 'can_export' => true ) );
			$esses = array_fill( 0, count( $post_types ), '%s' );
			$where = $wpdb->prepare( "{$wpdb->posts}.post_type IN (" . implode( ',', $esses ) . ')', $post_types );
		}

		if ( $args['status'] && ( 'post' == $args['post_type'] || 'page' == $args['post_type'] ) )
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_status = %s", $args['status'] );
		else
			$where .= " AND {$wpdb->posts}.post_status != 'auto-draft'";

		$join = '';
		if ( $args['category'] && 'post' == $args['post_type'] ) {
			if ( $term = term_exists( $args['category'], 'category' ) ) {
				$join = "INNER JOIN {$wpdb->term_relationships} ON ({$wpdb->posts}.ID = {$wpdb->term_relationships}.object_id)";
				$where .= $wpdb->prepare( " AND {$wpdb->term_relationships}.term_taxonomy_id = %d", $term['term_taxonomy_id'] );
			}
		}


		if ( $args['author'] )
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_author = %d", $args['author'] );

		if ( $args['start_date'] )
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_date >= %s", date( 'Y-m-d 00:00:00', strtotime( $args['start_date'] ) ) );

		if ( $args['end_date'] )
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_date <= %s", date( 'Y-m-d 23:59:59', strtotime( $args['end_date'] ) ) );

		// grab a snapshot of post IDs, just in case it changes during the export
		if ( empty( $args['post__in'] ) )
			$all_the_post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} $join WHERE $where ORDER BY post_date ASC, post_parent ASC" );
		else
			$all_the_post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE ID IN ({$args['post__in']}) ORDER BY post_date ASC, post_parent ASC" );

		// Make sure we're getting all of the attachments for these posts too
		if ( 'all' != $args['post_type'] || ! empty( $args['post__in'] ) ) {
			$all_post_ids_with_attachments = array();
			while ( $post_ids = array_splice( $all_the_post_ids, 0, 100 ) ) {
				$attachment_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_parent IN (". implode( ",", array_map( 'intval', $post_ids ) ) .")" );
				$all_post_ids_with_attachments = array_merge( $all_post_ids_with_attachments, $post_ids, (array)$attachment_ids );
			}
			$all_the_post_ids = $all_post_ids_with_attachments;
		}

		// get the requested terms ready, empty unless posts filtered by category or all content
		$cats = $tags = $terms = array();
		if ( isset( $term ) && $term ) {
			$cat = get_term( $term['term_id'], 'category' );
			$cats = array( $cat->term_id => $cat );
			unset( $term, $cat );
		} else if ( 'all' == $args['post_type'] ) {
			$categories = (array) get_categories( array( 'get' => 'all' ) );
			$tags = (array) get_tags( array( 'get' => 'all' ) );

			$custom_taxonomies = get_taxonomies( array( '_builtin' => false ) );
			$custom_terms = (array) get_terms( $custom_taxonomies, array( 'get' => 'all' ) );

			// put categories in order with no child going before its parent
			while ( $cat = array_shift( $categories ) ) {
				if ( $cat->parent == 0 || isset( $cats[$cat->parent] ) )
					$cats[$cat->term_id] = $cat;
				else
					$categories[] = $cat;
			}

			// put terms in order with no child going before its parent
			while ( $t = array_shift( $custom_terms ) ) {
				if ( $t->parent == 0 || isset( $terms[$t->parent] ) )
					$terms[$t->term_id] = $t;
				else
					$custom_terms[] = $t;
			}

			unset( $categories, $custom_taxonomies, $custom_terms );
		}

		// Load the functions available in wp-admin/includes/export.php
		ob_start();
		export_wp( array( 'content' => 'page', 'start_date' => '1971-01-01', 'end_date' => '1971-01-02' ) );
		ob_end_clean();

		WP_CLI::line( 'Exporting ' . count( $all_the_post_ids ) . ' items to be broken into ' . ceil( count( $all_the_post_ids ) / $args['file_item_count'] ) . ' files' );
		WP_CLI::line( 'Exporting ' . count( $cats ) . ' cateogries' );
		WP_CLI::line( 'Exporting ' . count( $tags ) . ' tags' );
		WP_CLI::line( 'Exporting ' . count( $terms ) . ' terms' );
		WP_CLI::line();

		$file_count = 1;

		while ( $post_ids = array_splice( $all_the_post_ids, 0, $args['file_item_count'] ) ) {

			$full_path = $this->wxr_path . $file_name_base . '.' . str_pad( $file_count, 3, '0', STR_PAD_LEFT ) . '.xml';

			// Create the file if it doesn't exist
			if ( ! file_exists( $full_path ) ) {
				touch( $full_path );
			}

			if ( ! file_exists( $full_path ) ) {
				WP_CLI::error( "Failed to create file " . $full_path );
				exit;
			} else {
				WP_CLI::line( 'Writing to file ' . $full_path );
			}

			if ( !$this->verbose )
				$progress = new \cli\progress\Bar( 'Exporting',  count( $post_ids ) );

			$this->start_export();
			echo '<?xml version="1.0" encoding="' . get_bloginfo( 'charset' ) . "\" ?>\n";

?>
<!-- This is a WordPress eXtended RSS file generated by WordPress as an export of your site. -->
<!-- It contains information about your site's posts, pages, comments, categories, and other content. -->
<!-- You may use this file to transfer that content from one site to another. -->
<!-- This file is not intended to serve as a complete backup of your site. -->

<!-- To import this information into a WordPress site follow these steps: -->
<!-- 1. Log in to that site as an administrator. -->
<!-- 2. Go to Tools: Import in the WordPress admin panel. -->
<!-- 3. Install the "WordPress" importer from the list. -->
<!-- 4. Activate & Run Importer. -->
<!-- 5. Upload this file using the form provided on that page. -->
<!-- 6. You will first be asked to map the authors in this export file to users -->
<!--    on the site. For each author, you may choose to map to an -->
<!--    existing user on the site or to create a new user. -->
<!-- 7. WordPress will then import each of the posts, pages, comments, categories, etc. -->
<!--    contained in this file into your site. -->

<?php the_generator( 'export' ); ?>
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/<?php echo WXR_VERSION; ?>/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:wp="http://wordpress.org/export/<?php echo WXR_VERSION; ?>/"
>

<channel>
	<title><?php bloginfo_rss( 'name' ); ?></title>
	<link><?php bloginfo_rss( 'url' ); ?></link>
	<description><?php bloginfo_rss( 'description' ); ?></description>
	<pubDate><?php echo date( 'D, d M Y H:i:s +0000' ); ?></pubDate>
	<language><?php echo get_option( 'rss_language' ); ?></language>
	<wp:wxr_version><?php echo WXR_VERSION; ?></wp:wxr_version>
	<wp:base_site_url><?php echo wxr_site_url(); ?></wp:base_site_url>
	<wp:base_blog_url><?php bloginfo_rss( 'url' ); ?></wp:base_blog_url>

<?php wxr_authors_list(); ?>

<?php foreach ( $cats as $c ) : ?>
	<wp:category><wp:term_id><?php echo $c->term_id ?></wp:term_id><wp:category_nicename><?php echo $c->slug; ?></wp:category_nicename><wp:category_parent><?php echo $c->parent ? $cats[$c->parent]->slug : ''; ?></wp:category_parent><?php wxr_cat_name( $c ); ?><?php wxr_category_description( $c ); ?></wp:category>
<?php endforeach; ?>
<?php foreach ( $tags as $t ) : ?>
	<wp:tag><wp:term_id><?php echo $t->term_id ?></wp:term_id><wp:tag_slug><?php echo $t->slug; ?></wp:tag_slug><?php wxr_tag_name( $t ); ?><?php wxr_tag_description( $t ); ?></wp:tag>
<?php endforeach; ?>
<?php foreach ( $terms as $t ) : ?>
	<wp:term><wp:term_id><?php echo $t->term_id ?></wp:term_id><wp:term_taxonomy><?php echo $t->taxonomy; ?></wp:term_taxonomy><wp:term_slug><?php echo $t->slug; ?></wp:term_slug><wp:term_parent><?php echo $t->parent ? $terms[$t->parent]->slug : ''; ?></wp:term_parent><?php wxr_term_name( $t ); ?><?php wxr_term_description( $t ); ?></wp:term>
<?php endforeach; ?>
<?php if ( 'all' == $args['post_type'] ) wxr_nav_menu_terms(); ?>

	<?php do_action( 'rss2_head' ); ?>
	<?php $this->flush_export( $full_path, false ); ?>

<?php if ( $post_ids ) {
			global $wp_query, $post;
			$wp_query->in_the_loop = true; // Fake being in the loop.

			// fetch 20 posts at a time rather than loading the entire table into memory
			while ( $next_posts = array_splice( $post_ids, 0, 20 ) ) {

				$where = 'WHERE ID IN (' . join( ',', $next_posts ) . ')';
				$posts = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} $where" );

				// Begin Loop
				foreach ( $posts as $post ) {

					if ( !$this->verbose ) {
						$progress->tick();
					} else {
						WP_CLI::line( "Exporting post $post->ID" );
					}

					setup_postdata( $post );
					$is_sticky = is_sticky( $post->ID ) ? 1 : 0;
?>
	<item>
		<title><?php echo apply_filters( 'the_title_rss', $post->post_title ); ?></title>
		<link><?php the_permalink_rss() ?></link>
		<pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true ), false ); ?></pubDate>
		<dc:creator><?php echo get_the_author_meta( 'login' ); ?></dc:creator>
		<guid isPermaLink="false"><?php esc_url( the_guid() ); ?></guid>
		<description></description>
		<content:encoded><?php echo wxr_cdata( apply_filters( 'the_content_export', $post->post_content ) ); ?></content:encoded>
		<excerpt:encoded><?php echo wxr_cdata( apply_filters( 'the_excerpt_export', $post->post_excerpt ) ); ?></excerpt:encoded>
		<wp:post_id><?php echo $post->ID; ?></wp:post_id>
		<wp:post_date><?php echo $post->post_date; ?></wp:post_date>
		<wp:post_date_gmt><?php echo $post->post_date_gmt; ?></wp:post_date_gmt>
		<wp:comment_status><?php echo $post->comment_status; ?></wp:comment_status>
		<wp:ping_status><?php echo $post->ping_status; ?></wp:ping_status>
		<wp:post_name><?php echo $post->post_name; ?></wp:post_name>
		<wp:status><?php echo $post->post_status; ?></wp:status>
		<wp:post_parent><?php echo $post->post_parent; ?></wp:post_parent>
		<wp:menu_order><?php echo $post->menu_order; ?></wp:menu_order>
		<wp:post_type><?php echo $post->post_type; ?></wp:post_type>
		<wp:post_password><?php echo $post->post_password; ?></wp:post_password>
		<wp:is_sticky><?php echo $is_sticky; ?></wp:is_sticky>
<?php if ( $post->post_type == 'attachment' ) : ?>
		<wp:attachment_url><?php echo wp_get_attachment_url( $post->ID ); ?></wp:attachment_url>
<?php  endif; ?>
<?php  wxr_post_taxonomy(); ?>
<?php $postmeta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE post_id = %d", $post->ID ) );
					foreach ( $postmeta as $meta ) :
						if ( apply_filters( 'wxr_export_skip_postmeta', false, $meta->meta_key, $meta ) )
							continue;
					?>
		<wp:postmeta>
			<wp:meta_key><?php echo $meta->meta_key; ?></wp:meta_key>
			<wp:meta_value><?php echo wxr_cdata( $meta->meta_value ); ?></wp:meta_value>
		</wp:postmeta>
<?php endforeach; ?>
<?php if ( false === $args['skip_comments'] ): ?>
<?php $comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved <> 'spam'", $post->ID ) );
					foreach ( $comments as $c ) : ?>
		<wp:comment>
			<wp:comment_id><?php echo $c->comment_ID; ?></wp:comment_id>
			<wp:comment_author><?php echo wxr_cdata( $c->comment_author ); ?></wp:comment_author>
			<wp:comment_author_email><?php echo $c->comment_author_email; ?></wp:comment_author_email>
			<wp:comment_author_url><?php echo esc_url_raw( $c->comment_author_url ); ?></wp:comment_author_url>
			<wp:comment_author_IP><?php echo $c->comment_author_IP; ?></wp:comment_author_IP>
			<wp:comment_date><?php echo $c->comment_date; ?></wp:comment_date>
			<wp:comment_date_gmt><?php echo $c->comment_date_gmt; ?></wp:comment_date_gmt>
			<wp:comment_content><?php echo wxr_cdata( $c->comment_content ) ?></wp:comment_content>
			<wp:comment_approved><?php echo $c->comment_approved; ?></wp:comment_approved>
			<wp:comment_type><?php echo $c->comment_type; ?></wp:comment_type>
			<wp:comment_parent><?php echo $c->comment_parent; ?></wp:comment_parent>
			<wp:comment_user_id><?php echo $c->user_id; ?></wp:comment_user_id>
<?php  $c_meta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->commentmeta WHERE comment_id = %d", $c->comment_ID ) );
					foreach ( $c_meta as $meta ) : ?>
			<wp:commentmeta>
				<wp:meta_key><?php echo $meta->meta_key; ?></wp:meta_key>
				<wp:meta_value><?php echo wxr_cdata( $meta->meta_value ); ?></wp:meta_value>
			</wp:commentmeta>
<?php  endforeach; ?>
		</wp:comment>
<?php endforeach; ?>
<?php endif; ?>
	</item>
<?php
				$this->flush_export( $full_path );
				}
			}
		} ?>
</channel>
</rss>
<?php
			$this->flush_export( $full_path );
			$this->end_export();
			$this->stop_the_insanity();

			if ( !$this->verbose ) {
				$progress->finish();
			}

			$file_count++;
		}
		WP_CLI::success( "All done with export" );
	}
}

WP_CLI::add_command( 'export', 'Export_Command' );

