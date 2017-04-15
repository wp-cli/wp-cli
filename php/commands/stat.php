<?php
/**
 * Explore your data.
 *
 * @package wp-cli
 */
class Stat_Command extends WP_CLI_Command {

	static $datums = array(
			'published_posts',
			'word_count',
		);

	static $group_bys = array(
			'',
			'category',
			'post_author',
		);

	static $periods = array(
			'day',
			'hour',
		);

	static $formats = array(
			'table',
			'json',
			'csv',
		);

	/**
	 * Stats for posts
	 *
	 * @subcommand post
	 * @synopsis <datum> [--group-by=<post-arg>] [--period=<period>] [--start-date=<yyyy-mm-dd>] [--end-date=<yyyy-mm-dd>] [--format=<format>]
	 */
	public function post( $args, $assoc_args ) {

		list( $datum ) = $args;

		$defaults = array(
				'group-by'            => '',
				'period'           => 'day',
				'start-date'       => date( 'Y-m-d', current_time( 'timestamp' ) ),
				'end-date'         => date( 'Y-m-d', current_time( 'timestamp' ) ),
				'format'           => 'table',
			);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$datum = sanitize_key( $datum );
		$group_by = sanitize_key( $assoc_args['group-by'] );
		$period = sanitize_key( $assoc_args['period'] );
		$start_date = date( 'Y-m-d H:i:s', strtotime( $assoc_args['start-date'] ) );
		$end_date = date( 'Y-m-d H:i:s', strtotime( '+1 day ' . $assoc_args['end-date'] ) );
		$format = sanitize_key( $assoc_args['format'] );

		foreach( array( 'datum', 'group_by', 'period', 'format' ) as $argument ) {

			$class_var = $argument . 's';
			if ( ! in_array( $$argument, self::$$class_var ) )
				WP_CLI::error( "Invalid {$argument} specified. Acceptable {$class_var}: " . implode( ', ', self::$$class_var ) );
		}

		if ( ! in_array( $format, self::$formats ) )
			WP_CLI::error( "Invalid format specified. Acceptable foramts: " . implode( ', ', self::$formats ) );

		if ( empty( $start_date ) || empty( $end_date ) )
			WP_CLI::error( "Invalid start_date or end_date." );

		$columns = array(
				'period',
			);
		$fields = array();
		switch( $group_by ) {
			case 'category':
			case 'post_tag':
				$terms = get_terms( $group_by );
				foreach( $terms as $term ) {
					$column = $term->slug . ':' . $datum;
					$columns[] = $column;
					$fields[$column] = $term->slug;
				}
				break;
			case 'post_author':
				$users = get_users();
				foreach( $users as $user ) {
					$column = $user->user_login . ':' . $datum;
					$columns[] = $column;
					$fields[$column] = $user->user_login;
				}
				break;
			default:
				$columns[] = $datum;
				$group_by = '';
				break;
		}

		$where_filter = function( $where ) use ( $start_date, $end_date ) {
			global $wpdb;

			$end_date = date( "Y-m-d H:i:s", strtotime( "+1 day", strtotime( $end_date ) ) );
			$where .= $wpdb->prepare( " AND ($wpdb->posts.post_date >= %s AND $wpdb->posts.post_date < %s)", $start_date, $end_date );
			return $where;
		};
		add_filter( 'posts_where', $where_filter );
		$args = array(
			'post_type'          => 'post',
			'post_status'        => 'publish',
			'order'              => 'ASC',
			'orderby'            => 'date',
			'update_term_cache'  => false,
			'update_meta_cache'  => false,
			'no_found_rows'      => true,
			'posts_per_page'     => -1,
		);
		$post_query = new WP_Query( $args );
		remove_filter( 'posts_where', $where_filter );

		$raw_posts = $post_query->posts;
		$period_posts = array();
		foreach( $raw_posts as $post ) {
			$post_period = $this->get_period_increment( $period, strtotime( $post->post_date ) );
			if ( ! empty( $group_by ) ) {
				foreach( $fields as $column => $value ) {
					if ( $this->is_post_valid( $post, $group_by, $value ) )
						$period_posts[$post_period][$column][] = $post;
				}
			} else {
				$period_posts[$post_period][] = $post;
			}
		}

		$output_stats = array();
		$period_increments = $this->get_period_increments( $period, $start_date, $end_date );
		foreach( $period_increments as $increment ) {
			$output_stat = new stdClass;
			$output_stat->period = $increment;

			if ( ! isset( $period_posts[$increment] ) )
				$period_posts[$increment] = array();

			if ( ! empty( $group_by ) ) {
				foreach( $fields as $column => $value ) {
					$output_stat->$column = $this->get_posts_datum( $datum, $period_posts[$increment][$column] );
				}
			} else {
				$output_stat->$datum = $this->get_posts_datum( $datum, $period_posts[$increment] );
			}
			$output_stats[] = $output_stat;
		}

		WP_CLI\Utils\format_items( $format, $columns, $output_stats );
	}

	/**
	 * Whether or not a post is valid for the group_by
	 */
	private function is_post_valid( $post, $group_by, $value ) {

		$ret = false;
		switch( $group_by ) {
			case 'category':
			case 'post_tag':
				$terms = get_the_terms( $post->ID, $group_by );
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) )
					$ret = in_array( $value, wp_list_pluck( $terms, 'slug' ) );
				break;
			case 'post_author':
				$post_author = get_user_by( 'id', $post->post_author );
				if ( $post_author->user_login == $value )
					$ret = true;
				break;
			default:
				break;
		}
		return $ret;
	}

	/**
	 * Get the datum for all of the provided posts
	 */
	private function get_posts_datum( $datum, $posts = array() ) {

		$value = 0;

		switch ( $datum ) {
			case 'published_posts':
				$value = count( $posts );
				break;
			case 'word_count':
				foreach( $posts as $post ) {
					$value += str_word_count( strip_tags( $post->post_content ) );
				}
				break;
		}
		return $value;
	}

	/**
	 * Given a period, get the close corresponding increment
	 */
	private function get_period_increment( $period, $timestamp ) {

		if ( ! $timestamp || ! is_int( $timestamp ) )
			return false;

		switch ( $period ) {
			case 'day':
				return date( 'Y-m-d', $timestamp );
				break;
			case 'hour':
				return date( 'Y-m-d H:00', $timestamp );
				break;
			default:
				return false;
				break;
		}

	}

	/**
	 * Get an array of period increments between a given start date and end date
	 */
	private function get_period_increments( $period, $start_date, $end_date ) {

		$increments = array();

		$start_time = strtotime( $start_date );
		$end_time = strtotime( $end_date );

		// This shouldn't ever happen
		if ( $end_time < $start_time )
			return $increments;

		$current_time = $start_time;
		while ( $current_time < $end_time ) {
			$increment = $this->get_period_increment( $period, $current_time );
			if ( $increment )
				$increments[$increment] = true;
			$current_time++;
		}

		$increments = array_keys( $increments );
		return $increments;
	}

}

WP_CLI::add_command( 'stat', 'Stat_Command' );