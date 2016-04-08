<?php
/**
 * Represents a set of posts and other site data to be exported.
 *
 * An immutable object, which gathers all data needed for the export.
 */
class WP_Export_Query {
	const QUERY_CHUNK = 100;

	private static $defaults = array(
		'post_ids' => null,
		'post_type' => null,
		'status' => null,
		'author' => null,
		'start_date' => null,
		'end_date' => null,
		'start_id' => null,
		'category' => null,
	);

	private $post_ids;
	private $filters;
	private $xml_gen;

	private $wheres = array();
	private $joins = array();

	private $author;
	private $category;

	public $missing_parents = false;

	public function __construct( $filters = array() ) {
		$this->filters = wp_parse_args( $filters, self::$defaults );
		$this->post_ids = $this->calculate_post_ids();
	}

	public function post_ids() {
		return $this->post_ids;
	}

	public function charset() {
		return get_bloginfo( 'charset' );
	}

	public function site_metadata() {
		$metadata = array(
			'name' => $this->bloginfo_rss( 'name' ),
			'url' => $this->bloginfo_rss( 'url' ),
			'language' => $this->bloginfo_rss( 'language' ),
			'description' => $this->bloginfo_rss( 'description' ),
			'pubDate' => date( 'D, d M Y H:i:s +0000' ),
			'site_url' => is_multisite()? network_home_url() : $this->bloginfo_rss( 'url' ),
			'blog_url' => $this->bloginfo_rss( 'url' ),
		);
		return $metadata;
	}

	public function wp_generator_tag() {
		return apply_filters( 'the_generator', get_the_generator( 'export' ), 'export' );
	}

	public function authors() {
		global $wpdb;
		$authors = array();
		$author_ids = $wpdb->get_col( "SELECT DISTINCT post_author FROM $wpdb->posts WHERE post_status != 'auto-draft'" );
		foreach ( (array) $author_ids as $author_id ) {
			$authors[] = get_userdata( $author_id );
		}
		$authors = array_filter( $authors );
		return $authors;
	}

	public function categories() {
		if ( $this->category ) {
			return array( $this->category );
		}
		if ( $this->filters['post_type'] ) {
			return array();
		}
		$categories = (array) get_categories( array( 'get' => 'all' ) );

		$this->check_for_orphaned_terms( $categories );

		$categories = self::topologically_sort_terms( $categories );

		return $categories;
	}

	public function tags() {
		if ( $this->filters['post_type'] ) {
			return array();
		}
		$tags = (array) get_tags( array( 'get' => 'all' ) );

		$this->check_for_orphaned_terms( $tags );

		return $tags;
	}

	public function custom_taxonomies_terms() {
		if ( $this->filters['post_type'] ) {
			return array();
		}
		$custom_taxonomies = get_taxonomies( array( '_builtin' => false ) );
		$custom_terms = (array) get_terms( $custom_taxonomies, array( 'get' => 'all' ) );
		$this->check_for_orphaned_terms( $custom_terms );
		$custom_terms = self::topologically_sort_terms( $custom_terms );
		return $custom_terms;
	}

	public function nav_menu_terms() {
		$nav_menus = wp_get_nav_menus();
		foreach( $nav_menus as &$term ) {
			$term->description = '';
		}
		return $nav_menus;
	}

	public function exportify_post( $post ) {
		$GLOBALS['wp_query']->in_the_loop = true;
		$previous_global_post = \WP_CLI\Utils\get_flag_value( $GLOBALS, 'post' );
		$GLOBALS['post'] = $post;
		setup_postdata( $post );
		$post->post_content = apply_filters( 'the_content_export', $post->post_content );
		$post->post_excerpt = apply_filters( 'the_excerpt_export', $post->post_excerpt );
		$post->is_sticky = is_sticky( $post->ID ) ? 1 : 0;
		$post->terms = self::get_terms_for_post( $post );
		$post->meta = self::get_meta_for_post( $post );
		$post->comments = $this->get_comments_for_post( $post );
		$GLOBALS['post'] = $previous_global_post;
		return $post;
	}

	public function posts() {
		$posts_iterator = new WP_Post_IDs_Iterator( $this->post_ids, self::QUERY_CHUNK );
		return new WP_Map_Iterator( $posts_iterator, array( $this, 'exportify_post' ) );
	}

	private function calculate_post_ids() {
		global $wpdb;
		if ( is_array( $this->filters['post_ids'] ) ) {
			return $this->filters['post_ids'];
		}
		$this->post_type_where();
		$this->status_where();
		$this->author_where();
		$this->start_date_where();
		$this->end_date_where();
		$this->start_id_where();
		$this->category_where();

		$where = implode( ' AND ', array_filter( $this->wheres ) );
		if ( $where ) $where = "WHERE $where";
		$join = implode( ' ', array_filter( $this->joins ) );

		$post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} AS p $join $where" );
		$post_ids = array_merge( $post_ids, $this->attachments_for_specific_post_types( $post_ids ) );
		return $post_ids;
	}

	private function post_type_where() {
		global $wpdb;
		$post_types_filters = array( 'can_export' => true );
		if ( $this->filters['post_type'] ) {
			$post_types = $this->filters['post_type'];

			// Flatten single post types
			if ( is_array( $post_types ) && 1 === count( $post_types ) ) {
				$post_types = array_shift( $post_types );
			}
			$post_types_filters = array_merge( $post_types_filters, array( 'name' => $post_types ) );
		}

		// Multiple post types
		if ( isset( $post_types_filters['name'] ) && is_array( $post_types_filters['name'] ) ) {
			$post_types = array();
			foreach ( $post_types_filters['name'] as $post_type ) {
				if ( post_type_exists( $post_type ) ) {
					$post_types[] = $post_type;
				}
			}
		} else {
			$post_types = get_post_types( $post_types_filters );
		}

		if ( ! $post_types ) {
			$this->wheres[] = 'p.post_type IS NULL';
			return;
		}
		$this->wheres[] = _wp_export_build_IN_condition( 'p.post_type', $post_types );
	}

	private function status_where() {
		global $wpdb;
		if ( !$this->filters['status'] ) {
			$this->wheres[] = "p.post_status != 'auto-draft'";
			return;
		}
		$this->wheres[] = $wpdb->prepare( 'p.post_status = %s', $this->filters['status'] );
	}

	private function author_where() {
		global $wpdb;
		$user = $this->find_user_from_any_object( $this->filters['author'] );
		if ( !$user || is_wp_error( $user ) ) {
			return;
		}
		$this->author = $user;
		$this->wheres[] = $wpdb->prepare( 'p.post_author = %d', $user->ID );
	}

	private function start_date_where() {
		global $wpdb;
		$timestamp = strtotime( $this->filters['start_date'] );
		if ( !$timestamp ) {
			return;
		}
		$this->wheres[] = $wpdb->prepare( 'p.post_date >= %s', date( 'Y-m-d 00:00:00', $timestamp ) );
	}

	private function end_date_where() {
		global $wpdb;
		if ( preg_match( '/^\d{4}-\d{2}$/', $this->filters['end_date'] ) ) {
			$timestamp = $this->get_timestamp_for_the_last_day_of_a_month( $this->filters['end_date'] );
		} else {
			$timestamp = strtotime( $this->filters['end_date'] );
		}
		if ( !$timestamp ) {
			return;
		}
		$this->wheres[] = $wpdb->prepare( 'p.post_date <= %s', date( 'Y-m-d 23:59:59', $timestamp ) );
	}

	private function start_id_where() {
		global $wpdb;

		$start_id = absint( $this->filters['start_id'] );
		if ( 0 === $start_id ) {
			return;
		}
		$this->wheres[] = $wpdb->prepare( 'p.ID >= %d', $start_id );
	}

	private function get_timestamp_for_the_last_day_of_a_month( $yyyy_mm ) {
		return strtotime( "$yyyy_mm +1month -1day" );
	}

	private function category_where() {
		global $wpdb;
		if ( 'post' != $this->filters['post_type'] && ! in_array( 'post', (array) $this->filters['post_type'] ) ) {
			return;
		}
		$category = $this->find_category_from_any_object( $this->filters['category'] );
		if ( !$category ) {
			return;
		}
		$this->category = $category;
		$this->joins[] = "INNER JOIN {$wpdb->term_relationships} AS tr ON (p.ID = tr.object_id)";
		$this->wheres[] = $wpdb->prepare( 'tr.term_taxonomy_id = %d', $category->term_taxonomy_id );
	}

	private function attachments_for_specific_post_types( $post_ids ) {
		global $wpdb;
		if ( !$this->filters['post_type'] ) {
			return array();
		}
		$attachment_ids = array();
		while ( $batch_of_post_ids = array_splice( $post_ids, 0, self::QUERY_CHUNK ) ) {
			$post_parent_condition = _wp_export_build_IN_condition( 'post_parent', $batch_of_post_ids );
			$attachment_ids = array_merge( $attachment_ids, (array)$wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND $post_parent_condition" ) );
		}
		return array_map( 'intval', $attachment_ids );
	}

	private function bloginfo_rss( $section ) {
		return apply_filters( 'bloginfo_rss', get_bloginfo_rss( $section ), $section );
	}

	private function find_user_from_any_object( $user ) {
		if ( is_numeric( $user ) ) {
			return get_user_by( 'id', $user );
		} elseif ( is_string( $user ) ) {
			return get_user_by( 'login', $user );
		} elseif ( isset( $user->ID ) ) {
			return get_user_by( 'id', $user->ID );
		}
		return false;
	}

	private function find_category_from_any_object( $category ) {
		if ( is_numeric( $category ) ) {
			return get_term( $category, 'category' );
		} elseif ( is_string( $category ) ) {
			$term = term_exists( $category, 'category' );
			return isset( $term['term_id'] )? get_term( $term['term_id'], 'category' ) : false;
		} elseif ( isset( $category->term_id ) ) {
			return get_term( $category->term_id, 'category' );
		}
		return false;
	}

	private static function topologically_sort_terms( $terms ) {
		$sorted = array();
		while ( $term = array_shift( $terms ) ) {
			if ( $term->parent == 0 || isset( $sorted[$term->parent] ) )
				$sorted[$term->term_id] = $term;
			else
				$terms[] = $term;
		}
		return $sorted;
	}

	private function check_for_orphaned_terms( $terms ) {
		$term_ids = array();
		$have_parent = array();

		foreach ( $terms as $term ) {
			$term_ids[ $term->term_id ] = true;
			if ( $term->parent != 0 )
				$have_parent[] = $term;
		}

		foreach ( $have_parent as $has_parent ) {
			if ( ! isset( $term_ids[ $has_parent->parent ] ) ) {
				$this->missing_parents = $has_parent;
				throw new WP_Export_Term_Exception( sprintf( __( 'Term is missing a parent: %s (%d)' ), $has_parent->slug, $has_parent->term_taxonomy_id ) );
			}
		}
	}

	private static function get_terms_for_post( $post ) {
		$taxonomies = get_object_taxonomies( $post->post_type );
		if ( empty( $taxonomies ) )
			return array();
		$terms = wp_get_object_terms( $post->ID, $taxonomies );
		$terms = $terms? $terms : array();
		return $terms;
	}

	private static function get_meta_for_post( $post ) {
		global $wpdb;
		$meta_for_export = array();
		$meta_from_db = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE post_id = %d", $post->ID ) );
		foreach ( $meta_from_db as $meta ) {
			if ( apply_filters( 'wxr_export_skip_postmeta', false, $meta->meta_key, $meta ) )
				continue;
			if ( in_array( $meta->meta_key, array( '_edit_lock', '_wp_attachment_metadata', '_wp_attached_file' ) ) ) {
				continue;
			}
			$meta_for_export[] = $meta;
		}
		return $meta_for_export;
	}

	private function get_comments_for_post( $post ) {
		global $wpdb;

		if ( isset( $this->filters['skip_comments'] ) ) {
			return array();
		}

		$comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved <> 'spam'", $post->ID ) );
		foreach( $comments as $comment ) {
			$meta = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->commentmeta WHERE comment_id = %d", $comment->comment_ID ) );
			$meta = $meta? $meta : array();
			$comment->meta = $meta;
		}
		return $comments;
	}
}

class WP_Export_Exception extends RuntimeException {
}

class WP_Export_Term_Exception extends RuntimeException {
}
