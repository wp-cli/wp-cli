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
	 * ## OPTIONS
	 *
	 * [--dir=<dirname>]
	 * : Full path to directory where WXR export files should be stored. Defaults
	 * to current working directory.
	 *
	 * [--skip_comments]
	 * : Don't export comments.
	 *
	 * [--max_file_size=<MB>]
	 * : A single export file should have this many megabytes.
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
	 * : Export only posts with this post_type. Defaults to all.
	 *
	 * [--post__in=<pid>]
	 * : Export all posts specified as a comma-separated list of IDs.
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
	 * ## EXAMPLES
	 *
	 *     wp export --dir=/tmp/ --user=admin --post_type=post --start_date=2011-01-01 --end_date=2011-12-31
	 *
	 *     wp export --dir=/tmp/ --post__in=123,124,125
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
			'max_file_size'   => 15,
		);

		$this->validate_args( wp_parse_args( $assoc_args, $defaults ) );

		if ( !function_exists( 'wp_export' ) ) {
			self::load_export_api();
		}

		WP_CLI::log( 'Starting export process...' );

		add_action( 'wp_export_new_file', function( $file_path ) {
			WP_CLI::log( sprintf( "Writing to file %s", $file_path ) );
		} );

		try {
			wp_export( array(
				'filters' => $this->export_args,
				'writer' => 'WP_Export_Split_Files_Writer',
				'writer_args' => array(
					'max_file_size' => $this->max_file_size * MB_IN_BYTES,
					'destination_directory' => $this->wxr_path,
					'filename_template' => self::get_filename_template()
				)
			) );
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		WP_CLI::success( 'All done with export.' );
	}

	private static function get_filename_template() {
		$sitename = sanitize_key( get_bloginfo( 'name' ) );
		if ( ! empty( $sitename ) ) {
			$sitename .= '.';
		}
		return $sitename . 'wordpress.' . date( 'Y-m-d' ) . '.%03d.xml';
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
			exit(1);
		}
	}

	private function check_dir( $path ) {
		if ( empty( $path ) ) {
			$path = getcwd();
		} elseif ( !is_dir( $path ) ) {
			WP_CLI::error( sprintf( "The directory %s does not exist", $path ) );
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
			WP_CLI::warning( sprintf( 'The post type %s does not exist. Choose "all" or any of these existing post types instead: %s', $post_type, implode( ", ", $post_types ) ) );
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
		// New exporter uses a different argument
		$this->export_args['post_ids'] = $post__in;
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

	private function check_max_file_size( $size ) {
		if ( !is_numeric( $size ) ) {
			WP_CLI::warning( sprintf( "max_file_size should be numeric", $size ) );
			return false;
		}

		$this->max_file_size = $size;

		return true;
	}
}

WP_CLI::add_command( 'export', 'Export_Command' );

