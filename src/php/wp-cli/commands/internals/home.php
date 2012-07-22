<?php

WP_CLI::add_command( 'home', function() {
	// The url for the wp-cli repository
	$repository_url = 'http://github.com/wp-cli/wp-cli';

	// Open the wp-cli page in the browser
	if ( exec( 'which x-www-browser' ) ) {
		system( 'x-www-browser '.$repository_url );
	}
	elseif ( exec( 'which open' ) ) {
		system( 'open '.$repository_url );
	}
	else {
		WP_CLI::error( 'No command found to open the homepage in the browser. Please open it manually: '.$repository_url );
		return;
	}

	WP_CLI::success( 'The wp-cli homepage should be opening in your browser.' );
} );

