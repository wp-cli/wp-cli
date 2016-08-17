<?php
/**
 * Manage terms.
 *
 * ## EXAMPLES
 *
 *     # Create term
 *     $ wp term create category Apple --description="A type of fruit"
 *     Success: Created category 199.
 *
 *     # Get term
 *     $ wp term get category 199 --format=json --fields=term_id,name,slug,count
 *     {"term_id":199,"name":"Apple","slug":"apple","count":1}
 *
 *     # Update term
 *     $ wp term update category 15 --name=Apple
 *     Success: Term updated.
 *
 *     # Get term url
 *     $ wp term url post_tag 123
 *     http://example.com/tag/tips-and-tricks
 *
 *     # Delete post category
 *     $ wp term delete category 15
 *     Success: Deleted category 15.
 *
 *     # Recount posts assigned to each categories and tags
 *     $ wp term recount category post_tag
 *     Success: Updated category term count
 *     Success: Updated post_tag term count
 *
 * @package wp-cli
 */
class Term_Command extends WP_CLI_Command {

	private $fields = array(
		'term_id',
		'term_taxonomy_id',
		'name',
		'slug',
		'description',
		'parent',
		'count',
	);

	/**
	 * List terms in a taxonomy.
	 *
	 * ## OPTIONS
	 *
	 * <taxonomy>...
	 * : List terms of one or more taxonomies
	 *
	 * [--<field>=<value>]
	 * : Filter by one or more fields (see get_terms() $args parameter for a list of fields).
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each term.
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
	 *   - ids
	 *   - json
	 *   - count
	 *   - yaml
	 * ---
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each term:
	 *
	 * * term_id
	 * * term_taxonomy_id
	 * * name
	 * * slug
	 * * description
	 * * parent
	 * * count
	 *
	 * There are no optionally available fields.
	 *
	 * ## EXAMPLES
	 *
	 *     # List post categories
	 *     $ wp term list category --format=csv
	 *     term_id,term_taxonomy_id,name,slug,description,parent,count
	 *     2,2,aciform,aciform,,0,1
	 *     3,3,antiquarianism,antiquarianism,,0,1
	 *     4,4,arrangement,arrangement,,0,1
	 *     5,5,asmodeus,asmodeus,,0,1
	 *
	 *     # List post tags
	 *     $ wp term list post_tag --fields=name,slug
	 *     +-----------+-------------+
	 *     | name      | slug        |
	 *     +-----------+-------------+
	 *     | 8BIT      | 8bit        |
	 *     | alignment | alignment-2 |
	 *     | Articles  | articles    |
	 *     | aside     | aside       |
	 *     +-----------+-------------+
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		foreach ( $args as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				WP_CLI::error( "Taxonomy $taxonomy doesn't exist." );
			}
		}

		$formatter = $this->get_formatter( $assoc_args );

		$defaults = array(
			'hide_empty' => false,
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		if ( ! empty( $assoc_args['term_id'] ) ) {
			$term = get_term_by( 'id', $assoc_args['term_id'], $args[0] );
			$terms = array( $term );
		} else {
			$terms = get_terms( $args, $assoc_args );
		}

		$terms = array_map( function( $term ){
			$term->count = (int)$term->count;
			$term->parent = (int)$term->parent;
			return $term;
		}, $terms );

		if ( 'ids' == $formatter->format ) {
			$terms = wp_list_pluck( $terms, 'term_id' );
			echo implode( ' ', $terms );
		} else {
			$formatter->display_items( $terms );
		}
	}

	/**
	 * Create a term.
	 *
	 * ## OPTIONS
	 *
	 * <taxonomy>
	 * : Taxonomy for the new term.
	 *
	 * <term>
	 * : A name for the new term.
	 *
	 * [--slug=<slug>]
	 * : A unique slug for the new term. Defaults to sanitized version of name.
	 *
	 * [--description=<description>]
	 * : A description for the new term.
	 *
	 * [--parent=<term-id>]
	 * : A parent for the new term.
	 *
	 * [--porcelain]
	 * : Output just the new term id.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp term create category Apple --description="A type of fruit"
	 *     Success: Created category 199.
	 */
	public function create( $args, $assoc_args ) {

		list( $taxonomy, $term ) = $args;

		$defaults = array(
			'slug'        => sanitize_title( $term ),
			'description' => '',
			'parent'      => 0,
		);
		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		$porcelain = \WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain' );
		unset( $assoc_args['porcelain'] );

		// Compatibility for < WP 4.0
		if ( $assoc_args['parent'] > 0 && ! term_exists( (int) $assoc_args['parent'] ) ) {
			WP_CLI::error( 'Parent term does not exist.' );
		}

		$assoc_args = wp_slash( $assoc_args );
		$term = wp_slash( $term );
		$ret = wp_insert_term( $term, $taxonomy, $assoc_args );

		if ( is_wp_error( $ret ) ) {
			WP_CLI::error( $ret->get_error_message() );
		} else {
			if ( $porcelain )
				WP_CLI::line( $ret['term_id'] );
			else
				WP_CLI::success( sprintf( "Created %s %d.", $taxonomy, $ret['term_id'] ) );
		}
	}

	/**
	 * Get a taxonomy term
	 *
	 * ## OPTIONS
	 *
	 * <taxonomy>
	 * : Taxonomy of the term to get
	 *
	 * <term-id>
	 * : ID of the term to get
	 *
	 * [--field=<field>]
	 * : Instead of returning the whole term, returns the value of a single field.
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
	 *     $ wp term get category 199 --format=json
	 *     {"term_id":199,"name":"Apple","slug":"apple","term_group":0,"term_taxonomy_id":199,"taxonomy":"category","description":"A type of fruit","parent":0,"count":0,"filter":"raw"}
	 */
	public function get( $args, $assoc_args ) {

		list( $taxonomy, $term_id ) = $args;
		$term = get_term_by( 'id', $term_id, $taxonomy );
		if ( ! $term ) {
			WP_CLI::error( "Term doesn't exist." );
		}

		if ( empty( $assoc_args['fields'] ) ) {
			$term_array = get_object_vars( $term );
			$assoc_args['fields'] = array_keys( $term_array );
		}

		$term->count = (int) $term->count;
		$term->parent = (int) $term->parent;

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_item( $term );
	}

	/**
	 * Update a term.
	 *
	 * ## OPTIONS
	 *
	 * <taxonomy>
	 * : Taxonomy of the term to update.
	 *
	 * <term-id>
	 * : ID for the term to update.
	 *
	 * [--name=<name>]
	 * : A new name for the term.
	 *
	 * [--slug=<slug>]
	 * : A new slug for the term.
	 *
	 * [--description=<description>]
	 * : A new description for the term.
	 *
	 * [--parent=<term-id>]
	 * : A new parent for the term.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp term update category 15 --name=Apple
	 *     Success: Term updated.
	 */
	public function update( $args, $assoc_args ) {

		list( $taxonomy, $term_id ) = $args;

		$defaults = array(
			'name'        => null,
			'slug'        => null,
			'description' => null,
			'parent'      => null,
		);
		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		foreach( $assoc_args as $key => $value ) {
			if ( is_null( $value ) )
				unset( $assoc_args[$key] );
		}

		$assoc_args = wp_slash( $assoc_args );
		$ret = wp_update_term( $term_id, $taxonomy, $assoc_args );

		if ( is_wp_error( $ret ) )
			WP_CLI::error( $ret->get_error_message() );
		else
			WP_CLI::success( "Term updated." );
	}

	/**
	 * Delete a term.
	 *
	 * ## OPTIONS
	 *
	 * <taxonomy>
	 * : Taxonomy of the term to delete.
	 *
	 * <term-id>...
	 * : One or more IDs of terms to delete.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete post category
	 *     $ wp term delete category 15
	 *     Success: Deleted category 15.
	 *
	 *     # Delete all post tags
	 *     $ wp term list post_tag --field=term_id | xargs wp term delete post_tag
	 *     Success: Deleted post_tag 159.
	 *     Success: Deleted post_tag 160.
	 *     Success: Deleted post_tag 161.
	 */
	public function delete( $args ) {
		$taxonomy = array_shift( $args );

		foreach ( $args as $term_id ) {
			$ret = wp_delete_term( $term_id, $taxonomy );

			if ( is_wp_error( $ret ) ) {
				WP_CLI::warning( $ret );
			} else if ( $ret ) {
				WP_CLI::success( sprintf( "Deleted %s %d.", $taxonomy, $term_id ) );
			} else {
				WP_CLI::warning( sprintf( "%s %d doesn't exist.", $taxonomy, $term_id ) );
			}
		}
	}

	/**
	 * Generate some terms.
	 *
	 * ## OPTIONS
	 *
	 * <taxonomy>
	 * : The taxonomy for the generated terms.
	 *
	 * [--count=<number>]
	 * : How many terms to generate?
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--max_depth=<number>]
	 * : Generate child terms down to a certain depth.
	 * ---
	 * default: 1
	 * ---
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: progress
	 * options:
	 *   - progress
	 *   - ids
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate post categories.
	 *     $ wp term generate category --count=10
	 *     Generating terms  100% [=========] 0:02 / 0:02
	 *
	 *     # Add meta to every generated term.
	 *     $ wp term generate category --format=ids --count=3 | xargs -d ' ' -I % wp term meta add % foo bar
	 *     Success: Added custom field.
	 *     Success: Added custom field.
	 *     Success: Added custom field.
	 */
	public function generate( $args, $assoc_args ) {
		global $wpdb;

		list ( $taxonomy ) = $args;

		$defaults = array(
			'count' => 100,
			'max_depth' => 1,
		);

		extract( array_merge( $defaults, $assoc_args ), EXTR_SKIP );

		if ( !taxonomy_exists( $taxonomy ) ) {
			WP_CLI::error( sprintf( "'%s' is not a registered taxonomy.", $taxonomy ) );
		}

		$label = get_taxonomy( $taxonomy )->labels->singular_name;
		$slug = sanitize_title_with_dashes( $label );

		$hierarchical = get_taxonomy( $taxonomy )->hierarchical;

		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'progress' );

		$notify = false;
		if ( 'progress' === $format ) {
			$notify = \WP_CLI\Utils\make_progress_bar( 'Generating terms', $count );
		}

		$previous_term_id = 0;
		$current_parent = 0;
		$current_depth = 1;

		$max_id = (int) $wpdb->get_var( "SELECT term_taxonomy_id FROM $wpdb->term_taxonomy ORDER BY term_taxonomy_id DESC LIMIT 1" );

		$suspend_cache_invalidation = wp_suspend_cache_invalidation( true );
		$created = array();

		for ( $i = $max_id + 1; $i <= $max_id + $count; $i++ ) {

			if ( $hierarchical ) {

				if ( $previous_term_id && $this->maybe_make_child() && $current_depth < $max_depth ) {

					$current_parent = $previous_term_id;
					$current_depth++;

				} else if ( $this->maybe_reset_depth() ) {

					$current_parent = 0;
					$current_depth = 1;

				}

			}

			$args = array(
				'parent' => $current_parent,
				'slug' => $slug . "-$i",
			);

			$name = "$label $i";
			$term = wp_insert_term( $name, $taxonomy, $args );
			if ( is_wp_error( $term ) ) {
				WP_CLI::warning( $term );
			} else {
				$created[] = $term['term_id'];
				$previous_term_id = $term['term_id'];
				if ( 'ids' === $format ) {
					echo $term['term_id'];
					if ( $i < $max_id + $count ) {
						echo ' ';
					}
				}
			}

			if ( 'progress' === $format ) {
				$notify->tick();
			}
		}

		wp_suspend_cache_invalidation( $suspend_cache_invalidation );
		clean_term_cache( $created, $taxonomy );

		if ( 'progress' === $format ) {
			$notify->finish();
		}
	}

	/**
	 * Get term url
	 *
	 * ## OPTIONS
	 *
	 * <taxonomy>
	 * : Taxonomy of the term(s) to get.
	 *
	 * <term-id>...
	 * : One or more IDs of terms to get the URL.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp term url post_tag 123
	 *     http://example.com/tag/tips-and-tricks
	 */
	public function url( $args ) {
		$term_ids = array_slice( $args, 1 );
		foreach ( $term_ids as $term_id ) {
			$term_link = get_term_link( (int)$term_id, $args[0] );
			if ( $term_link && ! is_wp_error( $term_link ) ) {
				WP_CLI::line( $term_link );
			} else {
				WP_CLI::warning( sprintf( "Invalid term %s.", $term_id ) );
			}
		}
	}

	/**
	 * Recalculate number of posts assigned to each term.
	 *
	 * In instances where manual updates are made to the terms assigned to
	 * posts in the database, the number of posts associated with a term
	 * can become out-of-sync with the actual number of posts.
	 *
	 * This command runs wp_update_term_count() on the taxonomy's terms
	 * to bring the count back to the correct value.
	 *
	 * ## OPTIONS
	 *
	 * <taxonomy>...
	 * : One or more taxonomies to recalculate.
	 *
	 * ## EXAMPLES
	 *
	 *     # Recount posts assigned to each categories and tags
	 *     $ wp term recount category post_tag
	 *     Success: Updated category term count.
	 *     Success: Updated post_tag term count.
	 *
	 *     # Recount all listed taxonomies
	 *     $ wp taxonomy list --field=name | xargs wp term recount
	 *     Success: Updated category term count.
	 *     Success: Updated post_tag term count.
	 *     Success: Updated nav_menu term count.
	 *     Success: Updated link_category term count.
	 *     Success: Updated post_format term count.
	 */
	public function recount( $args ) {
		foreach( $args as $taxonomy ) {

			if ( ! taxonomy_exists( $taxonomy ) ) {
				WP_CLI::warning( sprintf( "Taxonomy %s does not exist.", $taxonomy ) );
			} else {

				$terms = get_terms( $taxonomy, array( 'hide_empty' => false ) );
				$term_taxonomy_ids = wp_list_pluck( $terms, 'term_taxonomy_id' );

				wp_update_term_count( $term_taxonomy_ids, $taxonomy );

				WP_CLI::success( sprintf( "Updated %s term count.", $taxonomy ) );
			}

		}
	}

	private function maybe_make_child() {
		// 50% chance of making child term
		return ( mt_rand(1, 2) == 1 );
	}

	private function maybe_reset_depth() {
		// 10% chance of reseting to root depth
		return ( mt_rand(1, 10) == 7 );
	}

	private function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->fields, 'term' );
	}
}

WP_CLI::add_command( 'term', 'Term_Command' );
