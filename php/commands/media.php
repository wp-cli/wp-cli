<?php

/**
 * Manage attachments.
 *
 * @package wp-cli
 */
class Media_Command extends WP_CLI_Command {

	/**
	 * Regenerate thumbnail(s).
	 *
	 * ## OPTIONS
	 *
	 * [<attachment-id>...]
	 * : One or more IDs of the attachments to regenerate.
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message.
	 *
	 * ## EXAMPLES
	 *
	 *     # re-generate all thumbnails, without confirmation
	 *     wp media regenerate --yes
	 *
	 *     # re-generate all thumbnails that have IDs between 1000 and 2000
	 *     seq 1000 2000 | xargs wp media regenerate
	 */
	function regenerate( $args, $assoc_args = array() ) {
		if ( empty( $args ) ) {
			WP_CLI::confirm( 'Do you realy want to regenerate all images?', $assoc_args );
		}

		$query_args = array(
			'post_type' => 'attachment',
			'post__in' => $args,
			'post_mime_type' => 'image',
			'post_status' => 'any',
			'posts_per_page' => -1,
			'fields' => 'ids'
		);

		$images = new WP_Query( $query_args );

		$count = $images->post_count;

		if ( !$count ) {
			WP_CLI::warning( 'No images found.' );
			return;
		}

		WP_CLI::log( sprintf( 'Found %1$d %2$s to regenerate.', $count,
			ngettext( 'image', 'images', $count ) ) );

		foreach ( $images->posts as $id ) {
			$this->_process_regeneration( $id );
		}

		WP_CLI::success( sprintf(
			'Finished regenerating %1$s.',
			ngettext('the image', 'all images', $count)
		) );
	}

	/**
	 * Create attachments from local files or from URLs.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to file or files to be imported. Supports the glob(3) capabilities of the current shell.
	 *     If file is recognized as a URL (for example, with a scheme of http or ftp), the file will be
	 *     downloaded to a temp file before being sideloaded.
	 *
	 * --post_id=<post_id>
	 * : ID of the post to attach the imported files to
	 *
	 * --title=<title>
	 * : Attachment title (post title field)
	 *
	 * --caption=<caption>
	 * : Caption for attachent (post excerpt field)
	 *
	 * --alt=<alt_text>
	 * : Alt text for image (saved as post meta)
	 *
	 * --desc=<description>
	 * : "Description" field (post content) of attachment post
	 *
	 * --featured_image
	 * : If set, set the imported image as the Featured Image of the post its attached to.
	 *
	 * ## EXAMPLES
	 *
	 *     # Import all jpgs in the current user's "Pictures" directory, not attached to any post
	 *     wp media import ~/Pictures/**\/*.jpg
	 *
	 *     # Import a local image and set it to be the post thumbnail for a post
	 *     wp media import ~/Downloads/image.png --post_id=123 --title="A downloaded picture" --featured_image
	 *
	 *     # Import an image from the web
	 *     wp media import http://s.wordpress.org/style/images/wp-header-logo.png --title='The WordPress logo' --alt="Semantic personal publishing"
	 *
	 * @synopsis <file>... [--post_id=<id>] [--title=<title>] [--caption=<caption>] [--alt=<text>] [--desc=<description>] [--featured_image]
	 */
	function import( $args, $assoc_args = array() ) {
		$assoc_args = wp_parse_args( $assoc_args, array(
			'title' => null,
			'caption' => null,
			'alt' => null,
			'desc' => null
		) );

		if ( isset( $assoc_args['post_id'] ) ) {
			if ( !get_post( $assoc_args['post_id'] ) ) {
				WP_CLI::warning( "Invalid --post_id" );
				$assoc_args['post_id'] = false;
			}
		} else {
			$assoc_args['post_id'] = false;
		}

		foreach ( $args as $file ) {
			$is_file_remote = parse_url( $file, PHP_URL_SCHEME );
			$orig_filename = $file;

			if ( empty( $is_file_remote ) ) {
				if ( !file_exists( $file ) ) {
					WP_CLI::warning( "Unable to import file $file. Reason: File doesn't exist." );
					break;
				}
				$tempfile = $this->_make_copy( $file );
			} else {
				$tempfile = download_url( $file );
			}

			$file_array = array(
				'tmp_name' => $tempfile,
				'name' => basename( $file )
			);

			$post_array= array(
				'post_title' => $assoc_args['title'],
				'post_excerpt' => $assoc_args['caption'],
				'post_content' => $assoc_args['desc']
			);

			// Deletes the temporary file.
			$success = media_handle_sideload( $file_array, $assoc_args['post_id'], $assoc_args['title'], $post_array );

			// Set alt text
			if ( !is_wp_error( $success ) && $assoc_args['alt'] )
				update_post_meta( $success, '_wp_attachment_image_alt', $assoc_args['alt'] );

			// Set as featured image, if --post_id and --featured_image are set
			if ( !is_wp_error( $success ) && $assoc_args['post_id'] && isset($assoc_args['featured_image']) )
				update_post_meta( $assoc_args['post_id'], '_thumbnail_id', $success );

			$attachment_success_text = '';
			if ( $assoc_args['post_id'] ) {
				$attachment_success_text = " and attached to post {$assoc_args['post_id']}";
				if ( isset($assoc_args['featured_image']) )
					$attachment_success_text .= ' as featured image';
			}

			if ( is_wp_error( $success ) ) {
				WP_CLI::warning( sprintf(
					'Unable to import file %s. Reason: %s',
					$orig_filename, implode( ', ', $success->get_error_messages() )
				) );
			} else {
				WP_CLI::success( sprintf(
					'Imported file %s as attachment ID %d%s.',
					$orig_filename, $success, $attachment_success_text
				) );
			}
		}
	}

	/**
	 * Sideload embedded media, and update post references.
	 * 
	 * ## OPTIONS
	 * 
	 * --domain=<domain>
	 * : Only sideload media hosted on a specific domain.
	 * 
	 * [--post_type=<post-type>]
	 * : Only sideload media embedded in a specific post type.
	 * 
	 * [--verbose]
	 * : Show more information about the process on STDOUT.
	 * 
	 * @subcommand sideload
	 */
	public function sideload( $args, $assoc_args ) {
		global $wpdb;

		$defaults = array(
			'domain'      => '',
			'post_type'   => '',
			'verbose'     => false,
			);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$where_parts = array();

		$domain_str = '%' . esc_url_raw( $assoc_args['domain'] ) . '%';
		$where_parts[] = $wpdb->prepare( "post_content LIKE %s", $domain_str );

		if ( ! empty( $assoc_args['post_type'] ) )
			$where_parts[] = $wpdb->prepare( "post_type = %s", sanitize_key( $assoc_args['post_type'] ) );
		else
			$where_parts[] = "post_type NOT IN ('revision')";

		if ( ! empty( $where_parts ) )
			$where = 'WHERE ' . implode( ' AND ', $where_parts );
		else
			$where = '';

		$query = "SELECT ID, post_content FROM $wpdb->posts $where";

		$num_updated_posts = 0;
		foreach( new WP_CLI\Iterators\Query( $query ) as $post ) {

			$num_sideloaded_images = 0;

			if ( empty( $post->post_content ) )
				continue;

			$document = new DOMDocument;
			@$document->loadHTML( $post->post_content );

			$img_srcs = array();
			foreach( $document->getElementsByTagName( 'img' ) as $img ) {

				// Sometimes old content management systems put spaces in the URLs
				$img_src = esc_url_raw( str_replace( ' ', '%20', $img->getAttribute( 'src' ) ) );
				if ( ! empty( $assoc_args['domain'] ) && $assoc_args['domain'] != parse_url( $img_src, PHP_URL_HOST ) )
					continue;

				// Don't permit the same media to be sideloaded twice for this post
				if ( in_array( $img_src, $img_srcs ) )
					continue;

				// Most of this was stolen from media_sideload_image
				$tmp = download_url( $img_src );
	
				// Set variables for storage
				// fix file filename for query strings
				preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $img_src, $matches );
				$file_array = array();
				$file_array['name'] = sanitize_file_name( urldecode( basename( $matches[0] ) ) );
				$file_array['tmp_name'] = $tmp;

				// If error storing temporarily, unlink
				if ( is_wp_error( $tmp ) ) {
					@unlink( $file_array['tmp_name'] );
					$file_array['tmp_name'] = '';
					WP_CLI::warning( $tmp->get_error_message() );
					continue;
				}

				// do the validation and storage stuff
				$id = media_handle_sideload( $file_array, $post->ID );
				// If error storing permanently, unlink
				if ( is_wp_error( $id ) ) {
					@unlink( $file_array['tmp_name'] );
					WP_CLI::warning( $id->get_error_message() );
					continue;
				}

				$new_img = wp_get_attachment_image_src( $id, 'full' );
				$post->post_content = str_replace( $img->getAttribute( 'src' ), $new_img[0], $post->post_content );
				$num_sideloaded_images++;
				$img_srcs[] = $img_src;

				if ( $assoc_args['verbose'] )
					WP_CLI::line( sprintf( "Replaced '%s' with '%s' for post #%d", $img_src, $new_img[0], $post->ID ) );

			}

			if ( $num_sideloaded_images ) {
				$num_updated_posts++;
				$wpdb->update( $wpdb->posts, array( 'post_content' => $post->post_content ), array( 'ID' => $post->ID ) );
				clean_post_cache( $post->ID );
				if ( $assoc_args['verbose'] )
					WP_CLI::line( sprintf( "Sideloaded %d media references for post #%d", $num_sideloaded_images, $post->ID ) );
			} else if ( ! $num_sideloaded_images && $assoc_args['verbose'] ) {
				WP_CLI::line( sprintf( "No media sideloading necessary for post #%d", $post->ID ) );
			}
		}

		WP_CLI::success( sprintf( "Sideload complete. Updated media references for %d posts.", $num_updated_posts ) );
	}

	// wp_tempnam() inexplicably forces a .tmp extension, which spoils MIME type detection
	private function _make_copy( $path ) {
		$dir = get_temp_dir();
		$filename = basename( $path );
		if ( empty( $filename ) )
			$filename = time();

		$filename = $dir . wp_unique_filename( $dir, $filename );
		if ( !copy( $path, $filename ) )
			WP_CLI::error( "Could not create temporary file for $path" );

		return $filename;
	}

	private function _process_regeneration( $id ) {
		$image = get_post( $id );

		$fullsizepath = get_attached_file( $image->ID );

		$att_desc = sprintf( '"%1$s" (ID %2$d).', get_the_title( $image->ID ), $image->ID );

		if ( false === $fullsizepath || !file_exists( $fullsizepath ) ) {
			WP_CLI::warning( "Can't find $att_desc" );
			return;
		}

		$this->remove_old_images( $image->ID );

		$metadata = wp_generate_attachment_metadata( $image->ID, $fullsizepath );
		if ( is_wp_error( $metadata ) ) {
			WP_CLI::warning( $metadata->get_error_message() );
			return;
		}

		if ( empty( $metadata ) ) {
			WP_CLI::warning( "Couldn't regenerate thumbnails for $att_desc." );
			return;
		}

		wp_update_attachment_metadata( $image->ID, $metadata );

		WP_CLI::log( "Regenerated thumbnails for $att_desc" );

	}

	private function remove_old_images( $att_id ) {
		$wud = wp_upload_dir();

		$metadata = wp_get_attachment_metadata( $att_id );

		$dir_path = $wud['basedir'] . '/' . dirname( $metadata['file'] ) . '/';
		$original_path = $dir_path . basename( $metadata['file'] );

		foreach ( $metadata['sizes'] as $size => $size_info ) {
			$intermediate_path = $dir_path . $size_info['file'];

			if ( $intermediate_path == $original_path )
				continue;

			unlink( $intermediate_path );
		}
	}
}

WP_CLI::add_command( 'media', 'Media_Command', array(
	'before_invoke' => function () {
		if ( !wp_image_editor_supports() ) {
			WP_CLI::error( 'No support for generating images found. ' .
				'Please install the Imagick or GD PHP extensions.' );
		}
	}
) );

