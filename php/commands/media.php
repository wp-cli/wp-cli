<?php

/**
 * Functionality to control the media library and its attachments
 *
 * @package wp-cli
 */
class Media_Command extends WP_CLI_Command {
   
    function __construct() {
        WP_Filesystem();
    }

    /**
     * Regenerate thumbnail(s)
     *
     * @synopsis    <attachment-id>... [--yes]
     * props @benmay & @Viper007Bond
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

        WP_CLI::line( "Found {$count} " . ngettext('image', 'images', $count) . " to regenerate." );
 
        $not_found = array_diff( $args, $images->posts );
        if( !empty($not_found) ) {
            WP_CLI::warning( $this->_not_found_message( $not_found ) );
        }

        foreach ( $images->posts as $id ) {
            $this->_process_regeneration( $id );
        }

        WP_CLI::success( "Finished regenerating " . ngettext('the image', 'all images', $count) . ".");
    }

    private function _process_regeneration( $id ) {
        
        $image = get_post( $id );
        
        if ( !$image || 'attachment' != $image->post_type || 'image/' != substr( $image->post_mime_type, 0, 6 ) ) {
            WP_CLI::warning( "{$image->post_title} - invalid image ID." );
            return;
        }
        
        $fullsizepath = get_attached_file( $image->ID );
        
        if ( false === $fullsizepath || !file_exists( $fullsizepath ) ) {
            WP_CLI::warning( "{$image->post_title} - Can't find {$fullsizepath}." );
            return;
        }

        WP_CLI::line( "Start processing of \"" .  get_the_title( $image->ID ) . " (ID: {$image->ID})\"" );

        $a_path = explode( DIRECTORY_SEPARATOR, $fullsizepath );
        $a_file = explode( '.', $a_path[ count( $a_path ) - 1 ] );

        unset( $a_path );
        unset( $a_file[ count( $a_file ) - 1 ] );

        $image_name = $a_file[ count( $a_file ) - 1 ] . '-';
        $dir_path   = wp_upload_dir();

        // Read and delete files
        $dir   = opendir( $dir_path['basedir'] );
        $files = array();

        while ( $file = readdir( $dir ) ) {
        
            if ( !( strrpos( $file, $image_name ) === false ) ) {
                
                $thumbnail  = explode( $image_name, $file );
                $filename   = $thumbnail[ 1 ];

                //If we got the original / full image
                if ( "" == $thumbnail[ 0 ] ) {
                    $filetype       = wp_check_filetype($file);
                    $thumbnail_ext  = ".{$filetype['ext']}";
                    $thumbnail_name = basename( $filename, $thumbnail_ext );

                    $sizes          = explode( 'x', $thumbnail_name );
                    
                    // If not cropped by WP
                    if ( 2 == count( $sizes ) ) {
                        $width  = $sizes[0];
                        $height = $sizes[1];
                        if ( is_numeric( $width ) && is_numeric( $height ) ) {
                            WP_CLI::line( "Thumbnail: {$width} x {$height} was deleted." );
                            @unlink( $dir_path['basedir'] . DIRECTORY_SEPARATOR . $image_name . $thumbnail_name . $thumbnail_ext );
                        }
                    }
                }
            }
        }
        
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

    private function _not_found_message( $not_found_ids ){
        $count = count($not_found_ids);
        return "Unable to find the " . ngettext('image', 'images', $count) . " (" . implode(", ", $not_found_ids) . "). Are you sure " . ngettext('it', 'they', $count) .  " " . ngettext('exist', 'exists', $count) . "?";
    }
}

WP_CLI::add_command( 'media', 'Media_Command' );