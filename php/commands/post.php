<?php

/**
 * Manage posts.
 *
 * ## EXAMPLES
 *
 *     # Create a new post.
 *     $ wp post create --post_type=post --post_title='A sample post'
 *     Success: Created post 123.
 *
 *     # Update an existing post.
 *     $ wp post update 123 --post_status=draft
 *     Success: Updated post 123.
 *
 *     # Delete an existing post.
 *     $ wp post delete 123
 *     Success: Trashed post 123.
 *
 * @package wp-cli
 */
class Post_Command extends \WP_CLI\CommandWithDBObject {

	protected $obj_type = 'post';
	protected $obj_fields = array(
		'ID',
		'post_title',
		'post_name',
		'post_date',
		'post_status'
	);

	public function __construct() {
		$this->fetcher = new \WP_CLI\Fetchers\Post;
	}

	/**
	 * Create a new post.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : Read post content from <file>. If this value is present, the
	 *     `--post_content` argument will be ignored.
	 *
	 *   Passing `-` as the filename will cause post content to
	 *   be read from STDIN.
	 *
	 * [--<field>=<value>]
	 * : Associative args for the new post. See wp_insert_post().
	 *
	 * [--edit]
	 * : Immediately open system's editor to write or edit post content.
	 *
	 *   If content is read from a file, from STDIN, or from the `--post_content`
	 *   argument, that text will be loaded into the editor.
	 *
	 * [--porcelain]
	 * : Output just the new post id.
	 *
	 * ## EXAMPLES
	 *
	 *     # Create post and schedule for future
	 *     $ wp post create --post_type=page --post_title='A future post' --post_status=future --post_date='2020-12-01 07:00:00'
	 *     Success: Created post 1921.
	 *
	 *     # Create post with content from given file
	 *     $ wp post create ./post-content.txt --post_category=201,345 --post_title='Post from file'
	 *     Success: Created post 1922.
	 */
	public function create( $args, $assoc_args ) {
		if ( ! empty( $args[0] ) ) {
			$assoc_args['post_content'] = $this->read_from_file_or_stdin( $args[0] );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'edit' ) ) {
			$input = \WP_CLI\Utils\get_flag_value( $assoc_args, 'post_content', '' );

			if ( $output = $this->_edit( $input, 'WP-CLI: New Post' ) )
				$assoc_args['post_content'] = $output;
			else
				$assoc_args['post_content'] = $input;
		}

		if ( isset( $assoc_args['post_category'] ) ) {
			$assoc_args['post_category'] = explode( ',', $assoc_args['post_category'] );
		}

		$assoc_args = wp_slash( $assoc_args );
		parent::_create( $args, $assoc_args, function ( $params ) {
			return wp_insert_post( $params, true );
		} );
	}

	/**
	 * Update one or more existing posts.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more IDs of posts to update.
	 *
	 * [<file>]
	 * : Read post content from <file>. If this value is present, the
	 *     `--post_content` argument will be ignored.
	 *
	 *   Passing `-` as the filename will cause post content to
	 *   be read from STDIN.
	 *
	 * --<field>=<value>
	 * : One or more fields to update. See wp_update_post().
	 *
	 * [--defer-term-counting]
	 * : Recalculate term count in batch, for a performance boost.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp post update 123 --post_name=something --post_status=draft
	 *     Success: Updated post 123.
	 */
	public function update( $args, $assoc_args ) {

		foreach( $args as $key => $arg ) {
			if ( is_numeric( $arg ) ) {
				continue;
			}

			$assoc_args['post_content'] = $this->read_from_file_or_stdin( $arg );
			unset( $args[ $key ] );
			break;
		}

		if ( isset( $assoc_args['post_category'] ) ) {
			$assoc_args['post_category'] = explode( ',', $assoc_args['post_category'] );
		}

		$assoc_args = wp_slash( $assoc_args );
		parent::_update( $args, $assoc_args, function ( $params ) {
			return wp_update_post( $params, true );
		} );
	}

	/**
	 * Launch system editor to edit post content.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the post to edit.
	 *
	 * ## EXAMPLES
	 *
	 *     # Launch system editor to edit post
	 *     $ wp post edit 123
	 */
	public function edit( $args, $_ ) {
		$post = $this->fetcher->get_check( $args[0] );

		$r = $this->_edit( $post->post_content, "WP-CLI post {$post->ID}" );

		if ( $r === false )
			\WP_CLI::warning( 'No change made to post content.', 'Aborted' );
		else
			$this->update( $args, array( 'post_content' => $r ) );
	}

	protected function _edit( $content, $title ) {
		$content = apply_filters( 'the_editor_content', $content );
		$output = \WP_CLI\Utils\launch_editor_for_input( $content, $title );
		return ( is_string( $output ) ) ?
			apply_filters( 'content_save_pre', $output ) : $output;
	}

	/**
	 * Get details about a post.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the post to get.
	 *
	 * [--field=<field>]
	 * : Instead of returning the whole post, returns the value of a single field.
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
	 *     # Save the post content to a file
	 *     $ wp post get 123 --field=content > file.txt
	 */
	public function get( $args, $assoc_args ) {
		$post = $this->fetcher->get_check( $args[0] );

		$post_arr = get_object_vars( $post );
		unset( $post_arr['filter'] );

		if ( empty( $assoc_args['fields'] ) ) {
			$assoc_args['fields'] = array_keys( $post_arr );
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_item( $post_arr );
	}

	/**
	 * Delete an existing post.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more IDs of posts to delete.
	 *
	 * [--force]
	 * : Skip the trash bin.
	 *
	 * [--defer-term-counting]
	 * : Recalculate term count in batch, for a performance boost.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete post skipping trash
	 *     $ wp post delete 123 --force
	 *     Success: Deleted post 123.
	 *
	 *     # Delete all pages
	 *     $ wp post delete $(wp post list --post_type='page' --format=ids)
	 *     Success: Trashed post 1164.
	 *     Success: Trashed post 1186.
	 *
	 *     # Delete all posts in the trash
	 *     $ wp post delete $(wp post list --post_status=trash --format=ids)
	 *     Success: Trashed post 1268.
	 *     Success: Trashed post 1294.
	 */
	public function delete( $args, $assoc_args ) {
		$defaults = array(
			'force' => false
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		parent::_delete( $args, $assoc_args, function ( $post_id, $assoc_args ) {
			$status = get_post_status( $post_id );
			$post_type = get_post_type( $post_id );
			$r = wp_delete_post( $post_id, $assoc_args['force'] );

			if ( $r ) {
				$action = $assoc_args['force'] || 'trash' === $status || 'revision' === $post_type ? 'Deleted' : 'Trashed';

				return array( 'success', "$action post $post_id." );
			} else {
				return array( 'error', "Failed deleting post $post_id." );
			}
		} );
	}

	/**
	 * Get a list of posts.
	 *
	 * ## OPTIONS
	 *
	 * [--<field>=<value>]
	 * : One or more args to pass to WP_Query.
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each post.
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
	 * These fields will be displayed by default for each post:
	 *
	 * * ID
	 * * post_title
	 * * post_name
	 * * post_date
	 * * post_status
	 *
	 * These fields are optionally available:
	 *
	 * * post_author
	 * * post_date_gmt
	 * * post_content
	 * * post_excerpt
	 * * comment_status
	 * * ping_status
	 * * post_password
	 * * to_ping
	 * * pinged
	 * * post_modified
	 * * post_modified_gmt
	 * * post_content_filtered
	 * * post_parent
	 * * guid
	 * * menu_order
	 * * post_type
	 * * post_mime_type
	 * * comment_count
	 * * filter
	 * * url
	 *
	 * ## EXAMPLES
	 *
	 *     # List post
	 *     $ wp post list --field=ID
	 *     568
	 *     829
	 *     1329
	 *     1695
	 *
	 *     # List posts in JSON
	 *     $ wp post list --post_type=post --posts_per_page=5 --format=json
	 *     [{"ID":1,"post_title":"Hello world!","post_name":"hello-world","post_date":"2015-06-20 09:00:10","post_status":"publish"},{"ID":1178,"post_title":"Markup: HTML Tags and Formatting","post_name":"markup-html-tags-and-formatting","post_date":"2013-01-11 20:22:19","post_status":"draft"}]
	 *
	 *     # List all pages
	 *     $ wp post list --post_type=page --fields=post_title,post_status
	 *     +-------------+-------------+
	 *     | post_title  | post_status |
	 *     +-------------+-------------+
	 *     | Sample Page | publish     |
	 *     +-------------+-------------+
	 *
	 *     # List ids of all pages and posts
	 *     $ wp post list --post_type=page,post --format=ids
	 *     15 25 34 37 198
	 *
	 *     # List given posts
	 *     $ wp post list --post__in=1,3
	 *     +----+--------------+-------------+---------------------+-------------+
	 *     | ID | post_title   | post_name   | post_date           | post_status |
	 *     +----+--------------+-------------+---------------------+-------------+
	 *     | 3  | Lorem Ipsum  | lorem-ipsum | 2016-06-01 14:34:36 | publish     |
	 *     | 1  | Hello world! | hello-world | 2016-06-01 14:31:12 | publish     |
	 *     +----+--------------+-------------+---------------------+-------------+
	 *
	 * @subcommand list
	 */
	public function list_( $_, $assoc_args ) {
		$formatter = $this->get_formatter( $assoc_args );

		$defaults = array(
			'posts_per_page' => -1,
			'post_status'    => 'any',
		);
		$query_args = array_merge( $defaults, $assoc_args );
		$query_args = self::process_csv_arguments_to_arrays( $query_args );
		if ( isset( $query_args['post_type'] ) && 'any' !== $query_args['post_type'] ) {
			$query_args['post_type'] = explode( ',', $query_args['post_type'] );
		}

		if ( 'ids' == $formatter->format ) {
			$query_args['fields'] = 'ids';
			$query = new WP_Query( $query_args );
			echo implode( ' ', $query->posts );
		} else if ( 'count' === $formatter->format ) {
			$query_args['fields'] = 'ids';
			$query = new WP_Query( $query_args );
			$formatter->display_items( $query->posts );
		} else {
			$query = new WP_Query( $query_args );
			$posts = array_map( function( $post ) {
				$post->url = get_permalink( $post->ID );
				return $post;
			}, $query->posts );
			$formatter->display_items( $posts );
		}
	}

	/**
	 * Generate some posts.
	 *
	 * Creates a specified number of new posts with dummy data.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<number>]
	 * : How many posts to generate?
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--post_type=<type>]
	 * : The type of the generated posts.
	 * ---
	 * default: post
	 * ---
	 *
	 * [--post_status=<status>]
	 * : The status of the generated posts.
	 * ---
	 * default: publish
	 * ---
	 *
	 * [--post_author=<login>]
	 * : The author of the generated posts.
	 * ---
	 * default:
	 * ---
	 *
	 * [--post_date=<yyyy-mm-dd>]
	 * : The date of the generated posts. Default: current date
	 *
	 * [--post_content]
	 * : If set, the command reads the post_content from STDIN.
	 *
	 * [--max_depth=<number>]
	 * : For hierarchical post types, generate child posts down to a certain depth.
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
	 *     # Generate posts.
	 *     $ wp post generate --count=10 --post_type=page --post_date=1999-01-04
	 *     Generating posts  100% [================================================] 0:01 / 0:04
	 *
	 *     # Generate posts with fetched content.
	 *     $ curl http://loripsum.net/api/5 | wp post generate --post_content --count=10
	 *       % Total    % Received % Xferd  Average Speed   Time    Time     Time  Current
	 *                                      Dload  Upload   Total   Spent    Left  Speed
	 *     100  2509  100  2509    0     0    616      0  0:00:04  0:00:04 --:--:--   616
	 *     Generating posts  100% [================================================] 0:01 / 0:04
	 *
	 *     # Add meta to every generated posts.
	 *     $ wp post generate --format=ids | xargs -d ' ' -I % wp post meta add % foo bar
	 *     Success: Added custom field.
	 *     Success: Added custom field.
	 *     Success: Added custom field.
	 */
	public function generate( $args, $assoc_args ) {
		global $wpdb;

		$defaults = array(
			'count' => 100,
			'max_depth' => 1,
			'post_type' => 'post',
			'post_status' => 'publish',
			'post_author' => false,
			'post_date' => current_time( 'mysql' ),
			'post_content' => '',
		);
		extract( array_merge( $defaults, $assoc_args ), EXTR_SKIP );

		// @codingStandardsIgnoreStart
		if ( !post_type_exists( $post_type ) ) {
			WP_CLI::error( sprintf( "'%s' is not a registered post type.", $post_type ) );
		}

		if ( $post_author ) {
			$user_fetcher = new \WP_CLI\Fetchers\User;
			$post_author = $user_fetcher->get_check( $post_author )->ID;
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'post_content' ) ) {
			$post_content = file_get_contents( 'php://stdin' );
		}

		// Get the total number of posts.
		$total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s", $post_type ) );

		$label = get_post_type_object( $post_type )->labels->singular_name;

		$hierarchical = get_post_type_object( $post_type )->hierarchical;

		$limit = $count + $total;

		$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'progress' );

		$notify = false;
		if ( 'progress' === $format ) {
			$notify = \WP_CLI\Utils\make_progress_bar( 'Generating posts', $count );
		}

		$previous_post_id = 0;
		$current_depth = 1;
		$current_parent = 0;

		for ( $i = $total; $i < $limit; $i++ ) {

			if ( $hierarchical ) {

				if( $this->maybe_make_child() && $current_depth < $max_depth ) {

					$current_parent = $previous_post_id;
					$current_depth++;

				} else if( $this->maybe_reset_depth() ) {

					$current_depth = 1;
					$current_parent = 0;

				}
			}

			$args = array(
				'post_type' => $post_type,
				'post_title' => "$label $i",
				'post_status' => $post_status,
				'post_author' => $post_author,
				'post_parent' => $current_parent,
				'post_name' => "post-$i",
				'post_date' => $post_date,
				'post_content' => $post_content,
			);

			$post_id = wp_insert_post( $args, true );
			if ( is_wp_error( $post_id ) ) {
				WP_CLI::warning( $post_id );
			} else {
				$previous_post_id = $post_id;
				if ( 'ids' === $format ) {
					echo $post_id;
					if ( $i < $limit - 1 ) {
						echo ' ';
					}
				}
			}

			if ( 'progress' === $format ) {
				$notify->tick();
			}
		}
		if ( 'progress' === $format ) {
			$notify->finish();
		}
		// @codingStandardsIgnoreEnd
	}

	private function maybe_make_child() {
		// 50% chance of making child post
		return ( mt_rand(1, 2) == 1 );
	}

	private function maybe_reset_depth() {
		// 10% chance of reseting to root depth
		return ( mt_rand(1, 10) == 7 );
	}

	/**
	 * Read post content from file or STDIN
	 *
	 * @param string $arg Supplied argument
	 * @return string
	 */
	private function read_from_file_or_stdin( $arg ) {
		if ( $arg !== '-' ) {
			$readfile = $arg;
			if ( ! file_exists( $readfile ) || ! is_file( $readfile ) ) {
				\WP_CLI::error( "Unable to read content from '$readfile'." );
			}
		} else {
			$readfile = 'php://stdin';
		}
		return file_get_contents( $readfile );
	}
}

WP_CLI::add_command( 'post', 'Post_Command' );
