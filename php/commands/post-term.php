<?php
/**
 * Manage post term associations
 *
 * ## EXAMPLES
 *
 * 	wp post-term add 22 post_tag --slug=lorem-ipsum
 * 	wp post-term get 83 category --format=json
 *
 * @author Felipe Lavín Z. <felipe@yukei.net>
 */
class Post_Term extends \WP_CLI_Command{

	/**
	 * Add a term to a post
	 *
	 * @synopsis <id> <taxonomy> [--slug=<slug>] [--term-id=<term-id>] [--replace]
	 */
	public function add( $args, $assoc_args ){
		if ( empty($assoc_args['slug']) && empty($assoc_args['term-id']) ) {
			WP_CLI::error("You must specify the term slug or the term_id");
		}

		if ( ! empty($assoc_args['term-id']) ) {
			$term = \absint( $assoc_args['term-id'] );
		} else {
			$term = $assoc_args['slug'];
		}

		list( $post_id, $taxonomy ) = $args;

		if ( !isset($post_id) ){
			WP_CLI::error("You must specify the post ID");
		}
		if ( !isset($taxonomy) ){
			WP_CLI::error("You must specify the term taxonomy");
		}

		// if replace is set, then append is false
		$append = !! empty( $assoc_args['replace'] );

		$set_term = \wp_set_object_terms( $post_id, $term, $taxonomy, $append );

		if ( \is_wp_error($success) ) {
			WP_CLI::warning( $success->get_error_mesage() );
		} else {
			WP_CLI::success("$taxonomy $term successfully added to post ID $post_id");
		}

	}

	/**
	 * Remove one term from the post associations
	 *
	 * @synopsis <id> <taxonomy> [--slug=<slug>] [--term-id=<term-id>]
	 */
	public function remove( $args, $assoc_args ){
		if ( empty($assoc_args['slug']) && empty($assoc_args['term-id']) ) {
			WP_CLI::error("You must specify the term slug or the term_id");
			return;
		}

		if ( ! empty($assoc_args['term-id']) ) {
			$term  = \absint( $assoc_args['term-id'] );
			$field = 'term_id';
		} else {
			$term  = $assoc_args['slug'];
			$field = 'slug';
		}

		list( $post_id, $taxonomy ) = $args;

		if ( !isset($post_id) ){
			WP_CLI::error("You must specify the post ID");
			return;
		}
		if ( !isset($taxonomy) ){
			WP_CLI::error("You must specify the term taxonomy");
			return;
		}

		$existing_terms = \wp_get_object_terms( $post_id, $taxonomy );
		if ( \is_wp_error($existing_terms) ) {
			WP_CLI::warning( $existing_terms->get_error_mesage() );
			return;
		}

		$terms_count = count( $existing_terms );

		$new_terms = array();
		foreach ( $existing_terms as $e_term ) {
			if ( $term != $e_term->$field ) {
				$new_terms[] = (int)$e_term->term_id;
			}
		}

		switch ( $terms_count - count( $new_terms ) ){
			case 1:
				// exactly one term less
				$update = \wp_set_object_terms( $post_id, $new_terms, $taxonomy, false );
				if ( \is_wp_error($update) ) {
					WP_CLI::error( $update->get_error_message() );
				} elseif ( is_string($update) ) {
					WP_CLI::error("$taxonomy $update could not be added to the post");
				} else {
					WP_CLI::success("$taxonomy $term removed from post $post_id");
				}
				return;
				break;
			case 0:
				// no terms less
				WP_CLI::warning("$taxonomy $term was not associated to post $post_id");
				return;
				break;
			default:
				// weird stuff
				WP_CLI::warning("Something weird happened... or didn't");
				break;
		}
	}


	/**
	 * Unlink the object from the taxonomy
	 *
	 * @synopsis <id> <taxonomy>
	 */
	public function delete( $args, $assoc_args ){
		list( $post_id, $taxonomy ) = $args;
		$post_id = absint( $post_id );
		$post = \get_post($post_id);
		WP_CLI::confirm( "Sure you want to remove all $taxonomy terms from \"{$post->post_title}\" [ID: $post_id]?");
		\wp_delete_object_term_relationships( absint($post_id), $taxonomy );
		WP_CLI::success("All $taxonomy terms where removed from post $post_id");
	}

	/**
	 * Get terms associated to a post
	 *
	 * @synopsis <id> <taxonomy> [--format=<format>]
	 */
	public function get( $args, $assoc_args ){

		list( $post_id, $taxonomy ) = $args;

		$terms = \wp_get_object_terms( absint($post_id), $taxonomy );

		if ( \is_wp_error( $terms ) ) {
			WP_CLI::warning( $terms->get_error_message() );
		} else {
			WP_CLI::print_value( $terms, $assoc_args );
		}
	}

}

WP_CLI::add_command( 'post-term', 'Post_Term' );