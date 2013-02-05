<?php

/**
 * Functionality to control the media library and its attachments
 *
 * @package wp-cli
 */
class Media_Command extends WP_CLI_Command {
    var $errors = false;
    function __construct() {
        WP_Filesystem();
    }

    /**
     * Import a file into the media library.
     *
     * @synopsis <filename> [--blog] [--zip=<zip>]
     */
    function import( $args, $assoc_args = array() ) {
    }

    /**
     * Regenerate thumbnail(s)
     *
     * @synopsis    [--id=<id>] 
     * @todo        [--file=<file>]
     * props @benmay
     */
    function regenerate( $args, $assoc_args = array() ) {
        global $wpdb;

        $vars = wp_parse_args( $assoc_args, array(
            'id' => false,
        ) );

        extract($vars, EXTR_SKIP);

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
        
        foreach ( $images as $image )
            $this->_process_regeneration( $image->ID );
        
        if ( $this->errors )
            WP_CLI::error( 'Finished regenerating images - however, there were some errors throughout.' );
        else
            WP_CLI::success( 'Finished - without any errors either!' );
    }

    private function _process_regeneration( $id ) {
        // Don't break the JSON result
        @error_reporting( 0 );
        
        $image = get_post( $id );
        
        if ( !$image || 'attachment' != $image->post_type || 'image/' != substr( $image->post_mime_type, 0, 6 ) ) {
            $this->errors = true;
            WP_CLI::line( "FAILED: {$image->post_title} - invalid image ID" );
            return;
        }
        
        $fullsizepath = get_attached_file( $image->ID );
        
        if ( false === $fullsizepath || !file_exists( $fullsizepath ) ) {
            $this->errors = true;
            WP_CLI::line( "FAILED: {$image->post_title} -  Can't find it $fullsizepath" );
            return;
        }
        
        // 5 minutes per image should be PLENTY
        @set_time_limit( 900 );
        
        $array_path = explode( DIRECTORY_SEPARATOR, $fullsizepath );
        $array_file = explode( '.', $array_path[ count( $array_path ) - 1 ] );
        
        $imageFormat = $array_file[ count( $array_file ) - 1 ];
        
        unset( $array_path[ count( $array_path ) - 1 ] );
        unset( $array_file[ count( $array_file ) - 1 ] );
        
        $imagePath = implode( DIRECTORY_SEPARATOR, $array_path ) . DIRECTORY_SEPARATOR . implode( '.', $array_file );
        
        
        /**
         * Continue
         */
        $dirPath   = explode( DIRECTORY_SEPARATOR, $imagePath );
        $imageName = sprintf( "%s-", $dirPath[ count( $dirPath ) - 1 ] );
        unset( $dirPath[ count( $dirPath ) - 1 ] );
        $dirPath = sprintf( "%s%s", implode( DIRECTORY_SEPARATOR, $dirPath ), DIRECTORY_SEPARATOR );
        
        // Read and delete files
        $dir   = opendir( $dirPath );
        $files = array();
        while ( $file = readdir( $dir ) ) {
            if ( !( strrpos( $file, $imageName ) === false ) ) {
                $thumbnail = explode( $imageName, $file );
                if ( $thumbnail[ 0 ] == "" ) {
                    $thumbnailFormat = substr( $thumbnail[ 1 ], -4 );
                    $thumbnail       = substr( $thumbnail[ 1 ], 0, strlen( $thumbnail[ 1 ] ) - 4 );
                    $thumbnail       = explode( 'x', $thumbnail );
                    if ( count( $thumbnail ) == 2 ) {
                        if ( is_numeric( $thumbnail[ 0 ] ) && is_numeric( $thumbnail[ 1 ] ) ) {
                            WP_CLI::line( "Thumbnail: {$thumbnail[0]} x {$thumbnail[1]} was deleted." );
                            @unlink( $dirPath . $imageName . $thumbnail[ 0 ] . 'x' . $thumbnail[ 1 ] . $thumbnailFormat );
                        }
                    }
                }
            }
        }
        
        $metadata = wp_generate_attachment_metadata( $image->ID, $fullsizepath );
        
        if ( is_wp_error( $metadata ) ) {
            WP_CLI::line( $metadata->get_error_message() );
            return;
        }
        
        if ( empty( $metadata ) ) {
            $this->errors = true;
            WP_CLI::line( 'Unknown failure reason.' );
            return;
        }
        wp_update_attachment_metadata( $image->ID, $metadata );
        WP_CLI::success( esc_html( get_the_title( $image->ID ) ) . " (ID {$image->ID}): All thumbnails were successfully regenerated in  " . timer_stop() . "  seconds " );
    }
}

WP_CLI::add_command( 'media', 'Media_Command' );