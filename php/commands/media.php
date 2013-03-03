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
     * @synopsis    [--id=<id>] [--yes]
     * props @benmay & @Viper007Bond
     */
    function regenerate( $args, $assoc_args = array() ) {
        global $wpdb;

        $vars = wp_parse_args( $assoc_args, array(
            'id'    => false
        ) );

        extract($vars, EXTR_SKIP);

        // If id is given, skip confirm because it is only one file
        if( !empty( $id ) ) {
            $assoc_args['yes'] = true;
        }

        WP_CLI::confirm('Do you realy want to regenerate all images?', $assoc_args);

        $where_clause = ( $id ) ? "AND ID = $id" : '';

        if ( !$images = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' $where_clause AND post_mime_type LIKE 'image/%' ORDER BY ID DESC" ) ) {
            if ( $id ) {
                WP_CLI::error( "Unable to find the image. Are you sure some it exists?" );
            } else {
                WP_CLI::error( "Unable to find any images. Are you sure some exist?" );
            }

            return;
        }
        
        WP_CLI::line( 'Found ' . count( $images ) . ' pictures to regenerate!' );
        
        foreach ( $images as $image ) {
            $this->_process_regeneration( $image->ID );
        }
        
        WP_CLI::success( 'Finished regenerating images' );
    }

    private function _process_regeneration( $id ) {
        
        $image = get_post( $id );
        
        if ( !$image || 'attachment' != $image->post_type || 'image/' != substr( $image->post_mime_type, 0, 6 ) ) {
            WP_CLI::warning( "{$image->post_title} - invalid image ID" );
            return;
        }
        
        $fullsizepath = get_attached_file( $image->ID );
        
        if ( false === $fullsizepath || !file_exists( $fullsizepath ) ) {
            WP_CLI::warning( "{$image->post_title} -  Can't find it $fullsizepath" );
            return;
        }
        
        $array_path = explode( DIRECTORY_SEPARATOR, $fullsizepath );
        $array_file = explode( '.', $array_path[ count( $array_path ) - 1 ] );
        
        $imageFormat = $array_file[ count( $array_file ) - 1 ];
        
        unset( $array_path[ count( $array_path ) - 1 ] );
        unset( $array_file[ count( $array_file ) - 1 ] );
        
        $imagePath = implode( DIRECTORY_SEPARATOR, $array_path ) . DIRECTORY_SEPARATOR . implode( '.', $array_file );
        $dirPath   = explode( DIRECTORY_SEPARATOR, $imagePath );
        $imageName = $dirPath[ count( $dirPath ) - 1 ] . "-";
        unset( $dirPath[ count( $dirPath ) - 1 ] );
        $dirPath = implode( DIRECTORY_SEPARATOR, $dirPath ) . DIRECTORY_SEPARATOR;

        // Read and delete files
        $dir   = opendir( $dirPath );
        $files = array();
        while ( $file = readdir( $dir ) ) {
            
            if ( !( strrpos( $file, $imageName ) === false ) ) {
                
                $thumbnail = explode( $imageName, $file );
                $filename = $thumbnail[ 1 ];

                //If we got the original / full image
                if ( "" == $thumbnail[ 0 ] ) {
                    preg_match('/\.[^\.]+$/i', $file, $ext);
                    $thumbnailFormat = $ext[0];
                    $thumbnail       = basename( $file, $thumbnailFormat );
                    
                    $sizes  = explode( 'x', $thumbnail );                 
                    
                    // If not cropped by WP
                    if ( 2 == count( $sizes ) ) {
                        $width  = $sizes[0];
                        $height = $sizes[1];
                        if ( is_numeric( $width ) && is_numeric( $height ) ) {
                            WP_CLI::line( "Thumbnail: {$width} x {$height} was deleted." );
                            @unlink( $dirPath . $imageName . $width . 'x' . $thumbnail . $thumbnailFormat );
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
            WP_CLI::warning( 'Unknown failure reason.' );
            return;
        }
        wp_update_attachment_metadata( $image->ID, $metadata );
        WP_CLI::success( esc_html( get_the_title( $image->ID ) ) . " (ID {$image->ID}): All thumbnails were successfully regenerated in  " . timer_stop() . "  seconds " );
    }
}

WP_CLI::add_command( 'media', 'Media_Command' );