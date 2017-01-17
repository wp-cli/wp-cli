<?php

/**
 * Manage exports.
 *
 * ## EXAMPLES
 *
 *     # Export posts published by the user between given start and end date
 *     $ wp export --dir=/tmp/ --user=admin --post_type=post --start_date=2011-01-01 --end_date=2011-12-31
 *     Starting export process...
 *     Writing to file /tmp/staging.wordpress.2016-05-24.000.xml
 *     Success: All done with export.
 *
 * @package wp-cli
 */
class Export_Command extends WP_CLI_Command {

	/**
	* Initialize the array of arguments that will be eventually be passed to export_wp.
	*
	* @var array
	*/
	public $export_args = array();

	/**
	 * Export WordPress content to a WXR file.
	 *
	 * Generates one or more WXR files containing authors, terms, posts,
	 * comments, and attachments. WXR files do not include site configuration
	 * (options) or the attachment files themselves.
	 *
	 * ## OPTIONS
	 *
	 * [--dir=<dirname>]
	 * : Full path to directory where WXR export files should be stored. Defaults
	 * to current working directory.
	 *
	 * [--skip_comments]
	 * : Don't include comments in the WXR export file.
	 *
	 * [--max_file_size=<MB>]
	 * : A single export file should have this many megabytes.
	 * ---
	 * default: 15
	 * ---
	 *
	 * ## FILTERS
	 *
	 * [--start_date=<date>]
	 * : Export only posts published after this date, in format YYYY-MM-DD.
	 *
	 * [--end_date=<date>]
	 * : Export only posts published before this date, in format YYYY-MM-DD.
	 *
	 * [--post_type=<post-type>]
	 * : Export only posts with this post_type. Separate multiple post types with a
	 * comma.
	 * ---
	 * default: any
	 * ---
	 *
	 * [--post_type__not_in=<post-type>]
	 * : Export all post types except those identified. Separate multiple post types
	 * with a comma. Defaults to none.
	 *
	 * [--post__in=<pid>]
	 * : Export all posts specified as a comma- or space-separated list of IDs.
	 *
	 * [--start_id=<pid>]
	 * : Export only posts with IDs greater than or equal to this post ID.
	 *
	 * [--author=<author>]
	 * : Export only posts by this author. Can be either user login or user ID.
	 *
	 * [--category=<name>]
	 * : Export only posts in this category.
	 *
	 * [--post_status=<status>]
	 * : Export only posts with this status.
	 *
	 * [--filename_format=<format>]
	 * : Use a custom format for export filenames. Defaults to '{site}.wordpress.{date}.{n}.xml'.
	 *
	 * ## EXAMPLES
	 *
	 *     # Export posts published by the user between given start and end date
	 *     $ wp export --dir=/tmp/ --user=admin --post_type=post --start_date=2011-01-01 --end_date=2011-12-31
	 *     Starting export process...
	 *     Writing to file /tmp/staging.wordpress.2016-05-24.000.xml
	 *     Success: All done with export.
	 *
	 *     # Export posts by IDs
	 *     $ wp export --dir=/tmp/ --post__in=123,124,125
	 *     Starting export process...
	 *     Writing to file /tmp/staging.wordpress.2016-05-24.000.xml
	 *     Success: All done with export.
	 *
	 *     # Export a random subset of content
	 *     $ wp export --post__in="$(wp post list --post_type=post --orderby=rand --posts_per_page=8 --format=ids)"
	 *     Starting export process...
	 *     Writing to file /var/www/example.com/public_html/staging.wordpress.2016-05-24.000.xml
	 *     Success: All done with export.
	 */
	public function __invoke( $_, $assoc_args ) {
		$defaults = array(
			'dir'               => NULL,
			'start_date'        => NULL,
			'end_date'          => NULL,
			'post_type'         => NULL,
			'post_type__not_in' => NULL,
			'author'            => NULL,
			'category'          => NULL,
			'post_status'       => NULL,
			'post__in'          => NULL,
			'start_id'          => NULL,
			'skip_comments'     => NULL,
			'max_file_size'     => 15,
			'filename_format'   => '{site}.wordpress.{date}.{n}.xml',
		);

		$assoc_args = wp_parse_args( $assoc_args, $defaults );
		$this->validate_args( $assoc_args );

		if ( !function_exists( 'wp_export' ) ) {
			self::load_export_api();
		}

		WP_CLI::log( 'Starting export process...' );

		add_action( 'wp_export_new_file', function( $file_path ) {
			WP_CLI::log( sprintf( "Writing to file %s", $file_path ) );
			WP_CLI\Utils\wp_clear_object_cache();
		} );

		try {
			wp_export( array(
				'filters' => $this->export_args,
				'writer' => 'WP_Export_Split_Files_Writer',
				'writer_args' => array(
					'max_file_size' => $this->max_file_size * MB_IN_BYTES,
					'destination_directory' => $this->wxr_path,
					'filename_template' => self::get_filename_template( $assoc_args['filename_format'] ),
				)
			) );
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		WP_CLI::success( 'All done with export.' );
	}

	private static function get_filename_template( $filename_format ) {
		$sitename = sanitize_key( get_bloginfo( 'name' ) );
		if ( empty( $sitename ) ) {
			$sitename = 'site';
		}
		return str_replace( array( '{site}', '{date}', '{n}' ), array( $sitename, date( 'Y-m-d' ), '%03d' ), $filename_format );
	}

	private static function load_export_api() {
		if ( !defined( 'KB_IN_BYTES' ) ) {
			// Constants for expressing human-readable data sizes
			// in their respective number of bytes.
			define( 'KB_IN_BYTES', 1024 );
			define( 'MB_IN_BYTES', 1024 * KB_IN_BYTES );
			define( 'GB_IN_BYTES', 1024 * MB_IN_BYTES );
			define( 'TB_IN_BYTES', 1024 * GB_IN_BYTES );
			define( 'PB_IN_BYTES', 1024 * TB_IN_BYTES );
			define( 'EB_IN_BYTES', 1024 * PB_IN_BYTES );
			define( 'ZB_IN_BYTES', 1024 * EB_IN_BYTES );
			define( 'YB_IN_BYTES', 1024 * ZB_IN_BYTES );
		}

		require WP_CLI_ROOT . '/php/export/functions.export.php';
	}

	private function validate_args( $args ) {
		$has_errors = false;

		foreach ( $args as $key => $value ) {
			if ( is_callable( array( $this, 'check_' . $key ) ) ) {
				$result = call_user_func( array( $this, 'check_' . $key ), $value );
				if ( false === $result )
					$has_errors = true;
			}
		}

		if ( $has_errors ) {
			WP_CLI::halt(1);
		}
	}

	private function check_dir( $path ) {
		if ( empty( $path ) ) {
			$path = getcwd();
		} elseif ( !is_dir( $path ) ) {
			WP_CLI::error( sprintf( "The directory '%s' does not exist.", $path ) );
			return false;
		}

		$this->wxr_path = trailingslashit( $path );

		return true;
	}

	private function check_start_date( $date ) {
		if ( is_null( $date ) )
			return true;

		$time = strtotime( $date );
		if ( !empty( $date ) && !$time ) {
			WP_CLI::warning( sprintf( "The start_date %s is invalid.", $date ) );
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
			WP_CLI::warning( sprintf( "The end_date %s is invalid.", $date ) );
			return false;
		}
		$this->export_args['end_date'] = date( 'Y-m-d', $time );
		return true;
	}

	private function check_post_type( $post_type ) {
		if ( is_null( $post_type ) || 'any' === $post_type )
			return true;

		$post_type = array_unique( array_filter( explode( ',', $post_type ) ) );
		$post_types = get_post_types();

		foreach ( $post_type as $type ) {
			if ( ! in_array( $type, $post_types ) ) {
				WP_CLI::warning( sprintf(
					'The post type %s does not exist. Choose "any" or any of these existing post types instead: %s',
					$type,
					implode( ", ", $post_types )
				) );
				return false;
			}
		}
		$this->export_args['post_type'] = $post_type;
		return true;
	}

	private function check_post_type__not_in( $post_type ) {
		if ( is_null( $post_type ) ) {
			return true;
		}

		$post_type = array_unique( array_filter( explode( ',', $post_type ) ) );
		$post_types = get_post_types();

		$keep_post_types = array();
		foreach ( $post_type as $type ) {
			if ( ! in_array( $type, $post_types ) ) {
				WP_CLI::warning( sprintf(
					'The post type %s does not exist. Use any of these existing post types instead: %s',
					$type,
					implode( ", ", $post_types )
				) );
				return false;
			}
		}
		$this->export_args['post_type'] = array_diff( $post_types, $post_type );
		return true;
	}

	private function check_post__in( $post__in ) {
		if ( is_null( $post__in ) )
			return true;

		$separator = false !== stripos( $post__in, ' ' ) ? ' ' : ',';
		$post__in = array_unique( array_map( 'intval', explode( $separator, $post__in ) ) );
		if ( empty( $post__in ) ) {
			WP_CLI::warning( "post__in should be comma-separated post IDs." );
			return false;
		}
		// New exporter uses a different argument.
		$this->export_args['post_ids'] = $post__in;
		return true;
	}

	private function check_start_id( $start_id ) {
		if ( is_null( $start_id ) ) {
			return true;
		}

		$start_id = intval( $start_id );

		// Post IDs must be greater than 0.
		if ( 0 >= $start_id ) {
			WP_CLI::warning( sprintf( __( 'Invalid start ID: %d' ), $start_id ) );
			return false;
		}

		$this->export_args['start_id'] = $start_id;
		return true;
	}

	private function check_author( $author ) {
		if ( is_null( $author ) )
			return true;

		$authors = get_users_of_blog();
		if ( empty( $authors ) || is_wp_error( $authors ) ) {
			WP_CLI::warning( sprintf( "Could not find any authors in this blog." ) );
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
			WP_CLI::warning( sprintf( 'Could not find a category matching %s.', $category ) );
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
			WP_CLI::warning( 'Could not find any post stati.' );
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
			WP_CLI::warning( 'skip_comments needs to be 0 (no) or 1 (yes).' );
			return false;
		}
		$this->export_args['skip_comments'] = $skip;
		return true;
	}

	private function check_max_file_size( $size ) {
		if ( !is_numeric( $size ) ) {
			WP_CLI::warning( sprintf( "max_file_size should be numeric.", $size ) );
			return false;
		}

		$this->max_file_size = $size;

		return true;
	}
}

WP_CLI::add_command( 'export', 'Export_Command' );
