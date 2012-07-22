<?php

WP_CLI::add_command( 'eval-file', function( $args, $assoc_args ) {
	if ( empty( $args ) ) {
		WP_CLI::line( "usage: wp eval-file <path>" );
		exit;
	}

	foreach ( $args as $file ) {
		if ( !file_exists( $file ) ) {
			WP_CLI::error( "'$file' does not exist." );
		} else {
			include( $file );
		}
	}
} );

