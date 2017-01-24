<?php

use WP_CLI\Utils;

/**
 * Manage attachments.
 *
 * ## EXAMPLES
 *
 *     # Re-generate all thumbnails, without confirmation.
 *     $ wp media regenerate --yes
 *     Found 3 images to regenerate.
 *     1/3 Regenerated thumbnails for "Sydney Harbor Bridge" (ID 760).
 *     2/3 Regenerated thumbnails for "Boardwalk" (ID 757).
 *     3/3 Regenerated thumbnails for "Sunburst Over River" (ID 756).
 *     Success: Regenerated 3 of 3 images.
 *
 *     # Import a local image and set it to be the featured image for a post.
 *     $ wp media import ~/Downloads/image.png --post_id=123 --title="A downloaded picture" --featured_image
 *     Success: Imported file '/home/person/Downloads/image.png' as attachment ID 1753 and attached to post 123 as featured image.
 *
 * @package wp-cli
 */
class Media_Command extends WP_CLI_Command {

	/**
	 * Regenerate thumbnails for one or more attachments.
	 *
	 * ## OPTIONS
	 *
	 * [<attachment-id>...]
	 * : One or more IDs of the attachments to regenerate.
	 *
	 * [--skip-delete]
	 * : Skip deletion of the original thumbnails. If your thumbnails are linked from sources outside your control, it's likely best to leave them around. Defaults to false.
	 *
	 * [--only-missing]
	 * : Only generate thumbnails for images missing image sizes.
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message. Confirmation only shows when no IDs passed as arguments.
	 *
	 * ## EXAMPLES
	 *
	 *     # Regenerate thumbnails for given attachment IDs.
	 *     $ wp media regenerate 123 124 125
	 *     Found 3 images to regenerate.
	 *     1/3 Regenerated thumbnails for "Vertical Image" (ID 123).
	 *     2/3 Regenerated thumbnails for "Horizontal Image" (ID 124).
	 *     3/3 Regenerated thumbnails for "Beautiful Picture" (ID 125).
	 *     Success: Regenerated 3 of 3 images.
	 *
	 *     # Regenerate all thumbnails, without confirmation.
	 *     $ wp media regenerate --yes
	 *     Found 3 images to regenerate.
	 *     1/3 Regenerated thumbnails for "Sydney Harbor Bridge" (ID 760).
	 *     2/3 Regenerated thumbnails for "Boardwalk" (ID 757).
	 *     3/3 Regenerated thumbnails for "Sunburst Over River" (ID 756).
	 *     Success: Regenerated 3 of 3 images.
	 *
	 *     # Re-generate all thumbnails that have IDs between 1000 and 2000.
	 *     $ seq 1000 2000 | xargs wp media regenerate
	 *     Found 4 images to regenerate.
	 *     1/4 Regenerated thumbnails for "Vertical Featured Image" (ID 1027).
	 *     2/4 Regenerated thumbnails for "Horizontal Featured Image" (ID 1022).
	 *     3/4 Regenerated thumbnails for "Unicorn Wallpaper" (ID 1045).
	 *     4/4 Regenerated thumbnails for "I Am Worth Loving Wallpaper" (ID 1023).
	 *     Success: Regenerated 4 of 4 images.
	 */
	function regenerate( $args, $assoc_args = array() ) {
		if ( empty( $args ) ) {
			WP_CLI::confirm( 'Do you really want to regenerate all images?', $assoc_args );
		}

		$skip_delete = \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-delete' );
		$only_missing = \WP_CLI\Utils\get_flag_value( $assoc_args, 'only-missing' );
		if ( $only_missing ) {
			$skip_delete = true;
		}

		$mime_types = array( 'image' );
		if ( Utils\wp_version_compare( '4.7', '>=' ) ) {
			$mime_types[] = 'application/pdf';
		}
		$query_args = array(
			'post_type' => 'attachment',
			'post__in' => $args,
			'post_mime_type' => $mime_types,
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

		$errored = false;
		$successes = $errors = 0;
		foreach ( $images->posts as $number => $id ) {
			if ( $this->process_regeneration( $id, $skip_delete, $only_missing, ( $number + 1 ) . '/' . $count ) ) {
				$successes++;
			} else {
				$errors++;
			}
		}

		Utils\report_batch_operation_results( 'image', 'regenerate', count( $images->posts ), $successes, $errors );
	}

	/**
	 * Create attachments from local files or URLs.
	 *
	 * ## OPTIONS
	 *
	 * <file>...
	 * : Path to file or files to be imported. Supports the glob(3) capabilities of the current shell.
	 *     If file is recognized as a URL (for example, with a scheme of http or ftp), the file will be
	 *     downloaded to a temp file before being sideloaded.
	 *
	 * [--post_id=<post_id>]
	 * : ID of the post to attach the imported files to.
	 *
	 * [--title=<title>]
	 * : Attachment title (post title field).
	 *
	 * [--caption=<caption>]
	 * : Caption for attachent (post excerpt field).
	 *
	 * [--alt=<alt_text>]
	 * : Alt text for image (saved as post meta).
	 *
	 * [--desc=<description>]
	 * : "Description" field (post content) of attachment post.
	 *
	 * [--featured_image]
	 * : If set, set the imported image as the Featured Image of the post its attached to.
	 *
	 * [--porcelain]
	 * : Output just the new attachment ID.
	 *
	 * ## EXAMPLES
	 *
	 *     # Import all jpgs in the current user's "Pictures" directory, not attached to any post.
	 *     $ wp media import ~/Pictures/**\/*.jpg
	 *     Imported file '/home/person/Pictures/beautiful-youg-girl-in-ivy.jpg' as attachment ID 1751.
	 *     Imported file '/home/person/Pictures/fashion-girl.jpg' as attachment ID 1752.
	 *     Success: Imported 2 of 2 images.
	 *
	 *     # Import a local image and set it to be the post thumbnail for a post.
	 *     $ wp media import ~/Downloads/image.png --post_id=123 --title="A downloaded picture" --featured_image
	 *     Imported file '/home/person/Downloads/image.png' as attachment ID 1753 and attached to post 123 as featured image.
	 *     Success: Imported 1 of 1 images.
	 *
	 *     # Import a local image, but set it as the featured image for all posts.
	 *     # 1. Import the image and get its attachment ID.
	 *     # 2. Assign the attachment ID as the featured image for all posts.
	 *     $ ATTACHMENT_ID="$(wp media import ~/Downloads/image.png --porcelain)"
	 *     $ wp post list --post_type=post --format=ids | xargs -d ' ' -I % wp post meta add % _thumbnail_id $ATTACHMENT_ID
	 *     Success: Added custom field.
	 *     Success: Added custom field.
	 *
	 *     # Import an image from the web.
	 *     $ wp media import http://s.wordpress.org/style/images/wp-header-logo.png --title='The WordPress logo' --alt="Semantic personal publishing"
	 *     Imported file 'http://s.wordpress.org/style/images/wp-header-logo.png' as attachment ID 1755.
	 *     Success: Imported 1 of 1 images.
	 */
	function import( $args, $assoc_args = array() ) {
		$assoc_args = wp_parse_args( $assoc_args, array(
			'title' => '',
			'caption' => '',
			'alt' => '',
			'desc' => '',
		) );

		if ( isset( $assoc_args['post_id'] ) ) {
			if ( !get_post( $assoc_args['post_id'] ) ) {
				WP_CLI::warning( "Invalid --post_id" );
				$assoc_args['post_id'] = false;
			}
		} else {
			$assoc_args['post_id'] = false;
		}

		$successes = $errors = 0;
		foreach ( $args as $file ) {
			$is_file_remote = parse_url( $file, PHP_URL_HOST );
			$orig_filename = $file;

			if ( empty( $is_file_remote ) ) {
				if ( !file_exists( $file ) ) {
					WP_CLI::warning( "Unable to import file '$file'. Reason: File doesn't exist." );
					$errors++;
					break;
				}
				$tempfile = $this->make_copy( $file );
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
			$post_array = wp_slash( $post_array );

			// use image exif/iptc data for title and caption defaults if possible
			if ( empty( $post_array['post_title'] ) || empty( $post_array['post_excerpt'] ) ) {
				// @codingStandardsIgnoreStart
				$image_meta = @wp_read_image_metadata( $tempfile );
				// @codingStandardsIgnoreEnd
				if ( ! empty( $image_meta ) ) {
					if ( empty( $post_array['post_title'] ) && trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) ) {
						$post_array['post_title'] = $image_meta['title'];
					}

					if ( empty( $post_array['post_excerpt'] ) && trim( $image_meta['caption'] ) ) {
						$post_array['post_excerpt'] = $image_meta['caption'];
					}
				}
			}

			if ( empty( $post_array['post_title'] ) ) {
				$post_array['post_title'] = preg_replace( '/\.[^.]+$/', '', basename( $file ) );
			}

			// Deletes the temporary file.
			$success = media_handle_sideload( $file_array, $assoc_args['post_id'], $assoc_args['title'], $post_array );
			if ( is_wp_error( $success ) ) {
				WP_CLI::warning( sprintf(
					"Unable to import file '%s'. Reason: %s",
					$orig_filename, implode( ', ', $success->get_error_messages() )
				) );
				$errors++;
				continue;
			}

			// Set alt text
			if ( $assoc_args['alt'] ) {
				update_post_meta( $success, '_wp_attachment_image_alt', wp_slash( $assoc_args['alt'] ) );
			}

			// Set as featured image, if --post_id and --featured_image are set
			if ( $assoc_args['post_id'] && \WP_CLI\Utils\get_flag_value( $assoc_args, 'featured_image' ) ) {
				update_post_meta( $assoc_args['post_id'], '_thumbnail_id', $success );
			}

			$attachment_success_text = '';
			if ( $assoc_args['post_id'] ) {
				$attachment_success_text = " and attached to post {$assoc_args['post_id']}";
				if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'featured_image' ) )
					$attachment_success_text .= ' as featured image';
			}

			if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain' ) ) {
				WP_CLI::line( $success );
			} else {
				WP_CLI::log( sprintf(
					"Imported file '%s' as attachment ID %d%s.",
					$orig_filename, $success, $attachment_success_text
				) );
			}
			$successes++;
		}
		if ( ! Utils\get_flag_value( $assoc_args, 'porcelain' ) ) {
			Utils\report_batch_operation_results( 'image', 'import', count( $args ), $successes, $errors );
		}
	}

	// wp_tempnam() inexplicably forces a .tmp extension, which spoils MIME type detection
	private function make_copy( $path ) {
		$dir = get_temp_dir();
		$filename = basename( $path );
		if ( empty( $filename ) )
			$filename = time();

		$filename = $dir . wp_unique_filename( $dir, $filename );
		if ( !copy( $path, $filename ) )
			WP_CLI::error( "Could not create temporary file for $path." );

		return $filename;
	}

	private function process_regeneration( $id, $skip_delete, $only_missing, $progress ) {

		$fullsizepath = get_attached_file( $id );

		$att_desc = sprintf( '"%1$s" (ID %2$d)', get_the_title( $id ), $id );

		if ( false === $fullsizepath || !file_exists( $fullsizepath ) ) {
			WP_CLI::warning( "Can't find $att_desc." );
			return false;
		}

		if ( ! $skip_delete ) {
			$this->remove_old_images( $id );
		}

		if ( ! $only_missing || $this->needs_regeneration( $id ) ) {

			$metadata = wp_generate_attachment_metadata( $id, $fullsizepath );
			if ( is_wp_error( $metadata ) ) {
				WP_CLI::warning( $metadata->get_error_message() );
				return false;
			}

			if ( empty( $metadata ) ) {
				WP_CLI::warning( "$progress Couldn't regenerate thumbnails for $att_desc." );
				return false;
			}

			wp_update_attachment_metadata( $id, $metadata );

			WP_CLI::log( "$progress Regenerated thumbnails for $att_desc." );
			return true;
		} else {
			WP_CLI::log( "$progress No thumbnail regeneration needed for $att_desc." );
			return true;
		}
	}

	private function remove_old_images( $att_id ) {
		$wud = wp_upload_dir();

		$metadata = wp_get_attachment_metadata( $att_id );

		if ( empty( $metadata['file'] ) ) {
			return;
		}

		$dir_path = $wud['basedir'] . '/' . dirname( $metadata['file'] ) . '/';
		$original_path = $dir_path . basename( $metadata['file'] );

		if ( empty( $metadata['sizes'] ) ) {
			return;
		}

		foreach ( $metadata['sizes'] as $size_info ) {
			$intermediate_path = $dir_path . $size_info['file'];

			if ( $intermediate_path == $original_path )
				continue;

			if ( file_exists( $intermediate_path ) )
				unlink( $intermediate_path );
		}
	}

	private function needs_regeneration( $att_id ) {
		$wud = wp_upload_dir();

		$metadata = wp_get_attachment_metadata($att_id);

		if ( empty($metadata['file'] ) ) {
			return false;
		}

		$dir_path = $wud['basedir'] . '/' . dirname( $metadata['file'] ) . '/';
		$original_path = $dir_path . basename( $metadata['file'] );

		if ( empty( $metadata['sizes'] ) ) {
			return true;
		}

		if ( array_diff( get_intermediate_image_sizes(), array_keys( $metadata['sizes'] ) ) ) {
			return true;
		}

		foreach( $metadata['sizes'] as $size_info ) {
			$intermediate_path = $dir_path . $size_info['file'];

			if ( $intermediate_path == $original_path )
				continue;

			if ( ! file_exists( $intermediate_path ) ) {
				return true;
			}
		}
		return false;
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

