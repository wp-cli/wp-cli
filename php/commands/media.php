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
			_n( 'image', 'images', $count ) ) );

		foreach ( $images->posts as $id ) {
			$this->_process_regeneration( $id );
		}

		WP_CLI::success( sprintf(
			'Finished regenerating %1$s.',
			_n('the image', 'all images', $count)
		) );
	}

	/**
	 * Create attachments from local files or from URLs.
	 *
	 * ## OPTIONS
	 *
	 * <file>...
	 * : Path to file or files to be imported. Supports the glob(3) capabilities of the current shell.
	 *     If file is recognized as a URL (for example, with a scheme of http or ftp), the file will be
	 *     downloaded to a temp file before being sideloaded.
	 *
	 * [--post_id=<post_id>]
	 * : ID of the post to attach the imported files to
	 *
	 * [--title=<title>]
	 * : Attachment title (post title field)
	 *
	 * [--caption=<caption>]
	 * : Caption for attachent (post excerpt field)
	 *
	 * [--alt=<alt_text>]
	 * : Alt text for image (saved as post meta)
	 *
	 * [--desc=<description>]
	 * : "Description" field (post content) of attachment post
	 *
	 * [--featured_image]
	 * : If set, set the imported image as the Featured Image of the post its attached to.
	 *
	 * [--porcelain]
	 * : Output just the new attachment id.
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
			if ( is_wp_error( $success ) ) {
				WP_CLI::warning( sprintf(
					'Unable to import file %s. Reason: %s',
					$orig_filename, implode( ', ', $success->get_error_messages() )
				) );
				continue;
			}

			// Set alt text
			if ( $assoc_args['alt'] ) {
				update_post_meta( $success, '_wp_attachment_image_alt', $assoc_args['alt'] );
			}

			// Set as featured image, if --post_id and --featured_image are set
			if ( $assoc_args['post_id'] && isset( $assoc_args['featured_image'] ) ) {
				update_post_meta( $assoc_args['post_id'], '_thumbnail_id', $success );
			}

			$attachment_success_text = '';
			if ( $assoc_args['post_id'] ) {
				$attachment_success_text = " and attached to post {$assoc_args['post_id']}";
				if ( isset($assoc_args['featured_image']) )
					$attachment_success_text .= ' as featured image';
			}

			if ( isset( $assoc_args['porcelain'] ) ) {
				WP_CLI::line( $success );
			} else {
				WP_CLI::success( sprintf(
					'Imported file %s as attachment ID %d%s.',
					$orig_filename, $success, $attachment_success_text
				) );
			}
		}
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

		if ( empty( $metadata['sizes'] ) ) {
			return;
		}

		foreach ( $metadata['sizes'] as $size => $size_info ) {
			$intermediate_path = $dir_path . $size_info['file'];

			if ( $intermediate_path == $original_path )
				continue;

			if ( file_exists( $intermediate_path ) )
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

