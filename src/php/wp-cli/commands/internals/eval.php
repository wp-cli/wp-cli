<?php

WP_CLI::add_command( 'eval', function( $args, $assoc_args ) {
	if ( empty( $args ) ) {
		WP_CLI::line( "usage: wp eval '<php-code>'" );
		exit;
	}

	eval( $args[0] );
} );

