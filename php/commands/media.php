<?php

/**
 * Control the media library and its attachments.
 *
 * @package wp-cli
 */
class Media_Command extends WP_CLI_Command {

	/**
	 * Regenerate thumbnail(s)
	 *
	 * @synopsis <attachment-id>... [--yes]
	 */
	function regenerate( $args, $assoc_args = array() ) {
		global $wpdb;

		// If id is given, skip confirm because it is only one file
		if( !empty( $args ) ) {
			$assoc_args['yes'] = true;
		}

		WP_CLI::confirm('Do you realy want to regenerate all images?', $assoc_args);

		$query_args = array(
			'post_type' => 'attachment',
			'post__in' => $args,
			'post_mime_type' => 'image',
			'post_status' => 'any',
			'posts_per_page' => -1,
			'fields' => 'ids'
		);

		$images = new WP_Query( $query_args );

		if ( $images->post_count == 0 ) {
			//No images, so all keys in $args are not found within WP
			WP_CLI::error( $this->_not_found_message( $args ) );
		}
		$count = $images->post_count;

		WP_CLI::line( sprintf( 'Found %1$d %2$s to regenerate.', $count, ngettext('image', 'images', $count) ) );

		$not_found = array_diff( $args, $images->posts );
		if( !empty($not_found) ) {
			WP_CLI::warning( $this->_not_found_message( $not_found ) );
		}

		foreach ( $images->posts as $id ) {
			$this->_process_regeneration( $id );
		}

		WP_CLI::success( sprintf( 'Finished regenerating %1$s.', ngettext('the image', 'all images', $count) ) );
	}

	private function _process_regeneration( $id ) {
		$image = get_post( $id );

		$fullsizepath = get_attached_file( $image->ID );

		if ( false === $fullsizepath || !file_exists( $fullsizepath ) ) {
			WP_CLI::warning( "{$image->post_title} - Can't find {$fullsizepath}." );
			return;
		}

		WP_CLI::line( sprintf( 'Start processing of "%1$s" (ID %2$d).', get_the_title( $image->ID ), $image->ID ) );

		$this->remove_old_images( $image->ID );

		$metadata = wp_generate_attachment_metadata( $image->ID, $fullsizepath );

		if ( is_wp_error( $metadata ) ) {
			WP_CLI::warning( $metadata->get_error_message() );
			return;
		}

		if ( empty( $metadata ) ) {
			WP_CLI::warning( "Couldn't regenerate image." );
			return;
		}

		wp_update_attachment_metadata( $image->ID, $metadata );

		WP_CLI::success( "All thumbnails were successfully regenerated in " . timer_stop() . " seconds." );
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

			if ( unlink( $intermediate_path ) ) {
				WP_CLI::line( sprintf( "Thumbnail %s x %s was deleted.",
					$size_info['width'], $size_info['height'] ) );
			}
		}
	}

	private function _not_found_message( $not_found_ids ){
		$count = count( $not_found_ids );

		return vsprintf( 'Unable to find the %1$s (%2$s). Are you sure %3$s %4$s?', array(
			ngettext('image', 'images', $count),
			implode(", ", $not_found_ids),
			ngettext('it', 'they', $count),
			ngettext('exists', 'exist', $count),
		) );
	}
}

WP_CLI::add_command( 'media', 'Media_Command' );

